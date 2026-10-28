<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AvatarService;
use App\Support\RasterImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile photos: uploaded from the profile page, redrawn as a square WebP on
 * the private disk, and served only to signed in travellers.
 */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(AvatarService::disk());
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => $file]);
    }

    /** A PNG upload in three bands from top to bottom: red, green, blue. */
    private function bands(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $third = intdiv($height, 3);
        imagefilledrectangle($image, 0, 0, $width - 1, $third - 1, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 0, $third, $width - 1, 2 * $third - 1, imagecolorallocate($image, 0, 255, 0));
        imagefilledrectangle($image, 0, 2 * $third, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('bands.png', (string) ob_get_clean());
    }

    /** The colour in the middle of a stored image: 'red', 'green' or 'blue'. */
    private function middleColour(string $path): string
    {
        $image = imagecreatefromstring(Storage::disk(AvatarService::disk())->get($path));
        $rgb = imagecolorsforindex($image, imagecolorat($image, intdiv(imagesx($image), 2), intdiv(imagesy($image), 2)));
        $rgb = array_intersect_key($rgb, array_flip(['red', 'green', 'blue']));
        arsort($rgb);

        return array_key_first($rgb);
    }

    private function crop(User $user, array $crop)
    {
        return $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.avatar.crop'), $crop);
    }

    public function test_an_upload_is_stored_as_a_square_webp(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('me.jpg', 1200, 800))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $path = $user->refresh()->avatar_path;

        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression('#^avatars/'.$user->id.'-[A-Za-z0-9]{24}\.webp$#', $path);
        Storage::disk(AvatarService::disk())->assertExists($path);

        $size = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($path));
        $this->assertSame([512, 512, IMAGETYPE_WEBP], [$size[0], $size[1], $size[2]]);

        $this->actingAs($user)->get(route('profile.edit'))->assertSee($user->avatarUrl(), false);
    }

    public function test_a_small_photo_is_cropped_but_never_upscaled(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('small.png', 200, 120))->assertSessionHasNoErrors();

        $size = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->refresh()->avatar_path));
        $this->assertSame([120, 120], [$size[0], $size[1]]);
    }

    public function test_a_sideways_phone_photo_is_turned_upright(): void
    {
        $user = User::factory()->create();

        // Landscape pixels, red on the left and blue on the right, tagged by the
        // "camera" as needing a 90 degree clockwise turn (EXIF orientation 6).
        $image = imagecreatetruecolor(300, 200);
        imagefilledrectangle($image, 0, 0, 149, 199, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 150, 0, 299, 199, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();

        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"."\x00\x00\x00\x00";
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\x00\x00".$tiff;
        $jpeg = substr($jpeg, 0, 2).$app1.substr($jpeg, 2);

        $this->upload($user, UploadedFile::fake()->createWithContent('sideways.jpg', $jpeg))->assertSessionHasNoErrors();

        $stored = imagecreatefromstring(Storage::disk(AvatarService::disk())->get($user->refresh()->avatar_path));
        $top = imagecolorsforindex($stored, imagecolorat($stored, 100, 15));
        $bottom = imagecolorsforindex($stored, imagecolorat($stored, 100, 185));

        // Turned clockwise, the left (red) side ends up on top.
        $this->assertGreaterThan(180, $top['red']);
        $this->assertLessThan(90, $top['blue']);
        $this->assertGreaterThan(180, $bottom['blue']);
        $this->assertLessThan(90, $bottom['red']);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('first.jpg', 300, 300));
        $first = $user->refresh()->avatar_path;

        $this->upload($user, UploadedFile::fake()->image('second.jpg', 300, 300));
        $second = $user->refresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk(AvatarService::disk())->assertMissing($first);
        Storage::disk(AvatarService::disk())->assertExists($second);

        // Only the new photo's two files are left: its square and the whole photo.
        $this->assertCount(2, Storage::disk(AvatarService::disk())->allFiles());
    }

    public function test_the_square_chosen_in_the_cropper_is_cut_not_the_centre(): void
    {
        $user = User::factory()->create();

        // A tall photo: the centre square would be the green middle band.
        $this->actingAs($user)->from(route('profile.edit'))->post(route('profile.avatar.update'), [
            'avatar' => $this->bands(300, 900),
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_size' => 1 / 3,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('red', $this->middleColour($user->avatar_path));
        $this->assertEqualsWithDelta(['x' => 0, 'y' => 0, 'size' => 0.33333], $user->avatar_crop, 0.00001);

        // The whole photo is kept beside it, cleaned and private.
        $this->assertMatchesRegularExpression('#^avatars/originals/'.$user->id.'-[A-Za-z0-9]{24}\.webp$#', $user->avatar_original_path);
        $whole = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->avatar_original_path));
        $this->assertSame([300, 900, IMAGETYPE_WEBP], [$whole[0], $whole[1], $whole[2]]);

        // Zoomed in 3x on a 300px wide photo, the square is 100px: never blown up.
        $size = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->avatar_path));
        $this->assertSame([100, 100], [$size[0], $size[1]]);
    }

    public function test_without_a_chosen_square_the_centre_is_used(): void
    {
        $user = User::factory()->create();

        $this->upload($user, $this->bands(300, 900))->assertSessionHasNoErrors();

        $this->assertSame('green', $this->middleColour($user->refresh()->avatar_path));
        $this->assertEqualsWithDelta(['x' => 0, 'y' => 1 / 3, 'size' => 1], $user->avatar_crop, 0.00001);
    }

    public function test_a_big_photo_is_kept_scaled_down(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('big.jpg', 3200, 2400))->assertSessionHasNoErrors();

        $whole = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->refresh()->avatar_original_path));
        $this->assertSame([AvatarService::ORIGINAL_EDGE, 1200], [$whole[0], $whole[1]]);
    }

    public function test_the_square_can_be_moved_again_without_uploading(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => $this->bands(300, 900), 'crop_x' => 0, 'crop_y' => 0, 'crop_size' => 1 / 3,
        ]);
        $first = $user->refresh()->avatar_path;
        $original = $user->avatar_original_path;

        $this->crop($user, ['crop_x' => 0, 'crop_y' => 2 / 3, 'crop_size' => 1 / 3])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('blue', $this->middleColour($user->avatar_path));
        $this->assertEqualsWithDelta(2 / 3, $user->avatar_crop['y'], 0.00001);

        // A new file for the new square (so caches never show the old one); the whole photo stays.
        $this->assertNotSame($first, $user->avatar_path);
        Storage::disk(AvatarService::disk())->assertMissing($first);
        $this->assertSame($original, $user->avatar_original_path);
        Storage::disk(AvatarService::disk())->assertExists($original);
    }

    public function test_a_square_outside_the_photo_or_past_the_zoom_limit_is_pulled_back(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->bands(300, 900));

        // Asked for a 1% square in the far corner: it gets the smallest allowed, inside the photo.
        $this->crop($user, ['crop_x' => 1, 'crop_y' => 1, 'crop_size' => 0.01])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertEqualsWithDelta(['x' => 0.8, 'y' => 1 - 60 / 900, 'size' => 1 / AvatarService::MAX_ZOOM], $user->avatar_crop, 0.00001);
        $size = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->avatar_path));
        $this->assertSame([60, 60], [$size[0], $size[1]]);

        $this->crop($user, ['crop_x' => 2, 'crop_y' => -1, 'crop_size' => 'big'])
            ->assertSessionHasErrors(['crop_x', 'crop_y', 'crop_size']);
        $this->crop($user, [])->assertSessionHasErrors(['crop_x', 'crop_y', 'crop_size']);
    }

    public function test_photos_from_before_the_whole_picture_was_kept_can_still_be_adjusted(): void
    {
        $user = User::factory()->create();

        // An older upload: only the 512px square, no whole photo, no crop.
        $image = imagecreatetruecolor(512, 512);
        imagefilledrectangle($image, 0, 0, 511, 255, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 0, 256, 511, 511, imagecolorallocate($image, 0, 0, 255));
        $old = AvatarService::DIRECTORY.'/'.$user->id.'-'.str_repeat('a', 24).'.webp';
        Storage::disk(AvatarService::disk())->put($old, RasterImage::webp($image, 100));
        $user->forceFill(['avatar_path' => $old])->save();

        // The cropper opens on the square itself.
        $this->actingAs($user)->get(route('profile.avatar.original'))->assertOk();

        $this->crop($user, ['crop_x' => 0, 'crop_y' => 0.5, 'crop_size' => 0.5])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('blue', $this->middleColour($user->avatar_path));
        $this->assertNotSame($old, $user->avatar_path);
        Storage::disk(AvatarService::disk())->assertMissing($old);

        // The old square became the whole photo, so the next adjustment starts from all of it.
        $this->assertNotNull($user->avatar_original_path);
        $whole = getimagesizefromstring(Storage::disk(AvatarService::disk())->get($user->avatar_original_path));
        $this->assertSame([512, 512], [$whole[0], $whole[1]]);
    }

    public function test_adjusting_needs_a_photo(): void
    {
        $user = User::factory()->create();

        $this->crop($user, ['crop_x' => 0, 'crop_y' => 0, 'crop_size' => 1])->assertSessionHasErrors('avatar');
        $this->actingAs($user)->get(route('profile.avatar.original'))->assertNotFound();
    }

    public function test_the_whole_photo_only_goes_back_to_its_owner(): void
    {
        $user = User::factory()->create();
        $tripMate = User::factory()->create();

        $this->upload($user, $this->bands(300, 900));
        $original = $user->refresh()->avatar_original_path;

        $response = $this->actingAs($user)->get(route('profile.avatar.original'))->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(Storage::disk(AvatarService::disk())->get($original), $response->streamedContent());

        // Others see only the square: the photo route never serves the whole one.
        $this->actingAs($tripMate)->get(route('profile.avatar.original'))->assertNotFound();
        $this->actingAs($tripMate)->get(route('avatars.show', basename($original)))->assertNotFound();
        $this->actingAs($tripMate)->get('/avatars/originals/'.basename($original))->assertNotFound();

        auth()->logout();
        $this->get(route('profile.avatar.original'))->assertRedirect(route('login'));
    }

    public function test_a_photo_can_be_removed(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('me.jpg', 300, 300));
        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertNull($user->avatarUrl());
        $this->assertNull($user->avatar_original_path);
        $this->assertNull($user->avatar_crop);
        Storage::disk(AvatarService::disk())->assertMissing($path);
        $this->assertSame([], Storage::disk(AvatarService::disk())->allFiles());
    }

    public function test_files_that_are_not_usable_photos_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))->assertSessionHasErrors('avatar');
        $this->upload($user, UploadedFile::fake()->image('tiny.png', 40, 40))->assertSessionHasErrors('avatar');
        $this->upload($user, UploadedFile::fake()->image('huge.jpg', 400, 400)->size(AvatarService::MAX_KILOBYTES + 1))->assertSessionHasErrors('avatar');
        $this->upload($user, UploadedFile::fake()->createWithContent('fake.jpg', 'not really a picture'))->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame([], Storage::disk(AvatarService::disk())->allFiles());
    }

    public function test_the_profile_form_cannot_point_the_photo_at_another_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_path' => 'avatars/1-aaaaaaaaaaaaaaaaaaaaaaaa.webp',
        ]);

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_photos_are_shown_to_travellers_and_on_public_profiles_only(): void
    {
        $user = User::factory()->create();
        $tripMate = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('me.jpg', 300, 300));
        $url = $user->refresh()->avatarUrl();

        $response = $this->actingAs($tripMate)->get($url)->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));

        // Visitors see it because the profile is public...
        auth()->logout();
        $this->get($url)->assertOk();

        // ...and not once the traveller makes their profile private.
        $user->forceFill(['profile_public' => false])->save();
        $this->get($url)->assertNotFound();
        $this->actingAs($tripMate)->get($url)->assertOk();

        // A file nobody is using any more is not reachable.
        $old = $url;
        $this->upload($user, UploadedFile::fake()->image('new.jpg', 300, 300));
        $this->actingAs($tripMate)->get($old)->assertNotFound();
    }

    public function test_deleting_an_account_deletes_its_photo(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->image('me.jpg', 300, 300));
        $this->assertNotNull($user->refresh()->avatar_original_path);

        $user->delete();

        $this->assertSame([], Storage::disk(AvatarService::disk())->allFiles());
    }
}
