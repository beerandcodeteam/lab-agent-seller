<?php

use App\Models\CrmScan;
use App\Models\ScanStatus;

test('lookup_relationships_resolve', function () {
    $failed = CrmScan::factory()->failed()->create();
    CrmScan::factory()->success()->create();

    $status = ScanStatus::slug('failed');

    expect($status)->not->toBeNull();
    expect($status->scans->pluck('id'))->toContain($failed->id);
    expect($status->scans)->toHaveCount(1);
    expect($status->scans->first()->id)->toBe($failed->id);
});
