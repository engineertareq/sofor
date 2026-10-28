<?php

namespace Tests\Feature;

use App\Models\Sponsor;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\SponsorLogos;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use Tests\TestCase;

/**
 * Media on Cloudflare R2 (MEDIA_STORAGE=r2): sponsor logos in the public
 * bucket, profile photos in the private one, and the command that moves
 * existing local files over. R2 is faked; nothing here leaves the machine.
 */
class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_URL = 'https://media.sofor.test';

    private function useR2(bool $privateBucket = true): void
    {
        config([
            'media.storage' => 'r2',
            'media.disks.public' => 'r2',
            'media.disks.private' => $privateBucket ? 'r2_private' : 'local',
            'filesystems.disks.r2.url' => self::PUBLIC_URL,
        ]);

        Storage::fake('local');
        Storage::fake('r2', ['url' => self::PUBLIC_URL]);
        Storage::fake('r2_private');
    }

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->image('logo.png', 600, 200);
    }

    /* ------------------------------------------------------------------
       New uploads
    ------------------------------------------------------------------ */

    public function test_sponsor_logos_go_to_the_public_bucket_and_load_from_its_url(): void
    {
        $this->useR2();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.sponsors.store'), ['name' => 'Skyline Air', 'tier' => 'gold', 'is_active' => '1', 'logo' => $this->logo()])
            ->assertSessionHasNoErrors();

        $sponsor = Sponsor::firstOrFail();
        Storage::disk('r2')->assertExists($sponsor->logo_path);
        Storage::disk('local')->assertMissing($sponsor->logo_path);

        $url = self::PUBLIC_URL.'/'.$sponsor->logo_path;
        $this->assertSame($url, $sponsor->logoUrl());
        $this->get(route('sponsors'))->assertSee($url, false);

        // Old links through the app are sent on to the bucket.
        $this->get(route('sponsors.logo', basename($sponsor->logo_path)))->assertRedirect($url)->assertStatus(301);
    }

    public function test_profile_photos_go_to_the_private_bucket_and_are_still_served_by_the_app(): void
    {
        $this->useR2();

        $user = User::factory()->create(['profile_public' => false]);

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 800)])
            ->assertSessionHasNoErrors();

        $path = $user->refresh()->avatar_path;
        Storage::disk('r2_private')->assertExists($path);
        Storage::disk('local')->assertMissing($path);

        // Never a bucket address: the app still decides who sees a photo.
        $this->assertStringStartsWith(url('/avatars/'), $user->avatarUrl());

        $this->get($user->avatarUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');

        auth()->logout();
        $this->get($user->avatarUrl())->assertNotFound();
    }

    public function test_without_a_private_bucket_profile_photos_stay_local(): void
    {
        $this->useR2(privateBucket: false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 400)])
            ->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists($user->refresh()->avatar_path);
        Storage::disk('r2_private')->assertMissing($user->avatar_path);
    }

    public function test_a_failed_upload_to_r2_is_shown_as_a_form_error(): void
    {
        $this->useR2();
        Exceptions::fake();

        $broken = Mockery::mock(FilesystemAdapter::class);
        $broken->shouldReceive('put')->andThrow(UnableToWriteFile::atLocation('avatars/x.webp', 'R2 is unreachable'));
        Storage::set('r2_private', $broken);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 400)])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
        Exceptions::assertReported(UnableToWriteFile::class);
    }

    /* ------------------------------------------------------------------
       php artisan media:move-to-r2
    ------------------------------------------------------------------ */

    /** @return array{0: User, 1: Sponsor} a photo and a logo that only exist locally */
    private function localMedia(): array
    {
        $user = User::factory()->create(['avatar_path' => AvatarService::DIRECTORY.'/1-photo.webp']);
        $sponsor = Sponsor::create(['name' => 'Cha Corner', 'tier' => 'silver', 'logo_path' => SponsorLogos::DIRECTORY.'/logo.png']);

        Storage::disk('local')->put($user->avatar_path, 'photo-bytes');
        Storage::disk('local')->put($sponsor->logo_path, 'logo-bytes');

        return [$user, $sponsor];
    }

    public function test_existing_media_is_moved_to_the_right_buckets(): void
    {
        $this->useR2();
        [$user, $sponsor] = $this->localMedia();
        Storage::disk('local')->put(AvatarService::DIRECTORY.'/replaced-long-ago.webp', 'old');

        $this->artisan('media:move-to-r2')
            ->expectsOutputToContain('1 moved')
            ->expectsOutputToContain('1 local file(s) in avatars/ are not used by anything')
            ->assertSuccessful();

        $this->assertSame('photo-bytes', Storage::disk('r2_private')->get($user->avatar_path));
        $this->assertSame('logo-bytes', Storage::disk('r2')->get($sponsor->logo_path));
        Storage::disk('local')->assertMissing([$user->avatar_path, $sponsor->logo_path]);

        // Files nothing points at are reported, never touched.
        Storage::disk('local')->assertExists(AvatarService::DIRECTORY.'/replaced-long-ago.webp');

        // Running it again finds nothing left to do.
        $this->artisan('media:move-to-r2')->expectsOutputToContain('1 already on R2')->assertSuccessful();
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $this->useR2();
        [$user, $sponsor] = $this->localMedia();

        $this->artisan('media:move-to-r2', ['--dry-run' => true])
            ->expectsOutputToContain('would move '.$user->avatar_path)
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        Storage::disk('local')->assertExists([$user->avatar_path, $sponsor->logo_path]);
        Storage::disk('r2_private')->assertMissing($user->avatar_path);
        Storage::disk('r2')->assertMissing($sponsor->logo_path);
    }

    public function test_local_copies_can_be_kept(): void
    {
        $this->useR2();
        [$user] = $this->localMedia();

        $this->artisan('media:move-to-r2', ['--keep-local' => true])->assertSuccessful();

        Storage::disk('r2_private')->assertExists($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);
    }

    public function test_a_failed_upload_keeps_the_local_copy_and_fails_the_command(): void
    {
        $this->useR2();
        [$user] = $this->localMedia();

        $broken = Mockery::mock(FilesystemAdapter::class);
        $broken->shouldReceive('put')->andThrow(UnableToWriteFile::atLocation($user->avatar_path, 'NoSuchBucket'));
        Storage::set('r2_private', $broken);

        $this->artisan('media:move-to-r2')
            ->expectsOutputToContain('failed '.$user->avatar_path)
            ->assertFailed();

        Storage::disk('local')->assertExists($user->avatar_path);
    }

    public function test_photos_stay_put_without_a_private_bucket(): void
    {
        $this->useR2(privateBucket: false);
        [$user, $sponsor] = $this->localMedia();

        $this->artisan('media:move-to-r2')
            ->expectsOutputToContain('Profile photos: still set to the local disk')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($user->avatar_path);
        Storage::disk('r2')->assertExists($sponsor->logo_path);
    }

    public function test_the_move_refuses_to_run_until_media_is_on_r2(): void
    {
        $this->artisan('media:move-to-r2')
            ->expectsOutputToContain('Set MEDIA_STORAGE=r2')
            ->assertFailed();
    }
}
