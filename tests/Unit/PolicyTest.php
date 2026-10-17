<?php

use App\Models\Device;
use App\Models\MeasurementType;
use App\Models\User;
use App\Policies\DevicePolicy;
use App\Policies\MeasurementTypePolicy;

it('applies the device ownership rules', function () {
    $owner = User::factory()->create(['id' => 1]);
    $other = User::factory()->create(['id' => 2]);
    $admin = User::factory()->admin()->create(['id' => 3]);
    $private = Device::factory()->make(['user_id' => 1, 'is_public' => false]);
    $public = Device::factory()->make(['user_id' => 1, 'is_public' => true]);
    $p = new DevicePolicy;

    expect($p->view(null, $public))->toBeTrue()
        ->and($p->view(null, $private))->toBeFalse()
        ->and($p->view($other, $private))->toBeFalse()
        ->and($p->view($owner, $private))->toBeTrue()
        ->and($p->view($admin, $private))->toBeTrue()
        ->and($p->update($other, $public))->toBeFalse()
        ->and($p->update($owner, $public))->toBeTrue()
        ->and($p->delete($admin, $public))->toBeTrue()
        ->and($p->create($other))->toBeTrue();
});

it('lets only creators/admins change non-system measurement types', function () {
    $creator = User::factory()->create(['id' => 1]);
    $other = User::factory()->create(['id' => 2]);
    $admin = User::factory()->admin()->create(['id' => 3]);
    $custom = MeasurementType::factory()->make(['created_by' => 1, 'is_system' => false]);
    $system = MeasurementType::factory()->make(['created_by' => null, 'is_system' => true]);
    $p = new MeasurementTypePolicy;

    expect($p->update($creator, $custom))->toBeTrue()
        ->and($p->update($admin, $custom))->toBeTrue()
        ->and($p->delete($other, $custom))->toBeFalse()
        ->and($p->update($admin, $system))->toBeTrue()
        ->and($p->update($creator, $system))->toBeFalse();
});

it('scopes visibility per viewer', function () {
    $u = User::factory()->create();
    $mine = Device::factory()->for($u, 'owner')->private()->create();
    $pub = Device::factory()->public()->create();
    Device::factory()->private()->create();

    expect(Device::visibleTo(null)->pluck('id')->all())->toBe([$pub->id])
        ->and(Device::visibleTo($u)->pluck('id')->sort()->values()->all())->toBe([$mine->id, $pub->id])
        ->and(Device::visibleTo(User::factory()->admin()->create())->count())->toBe(3);
});
