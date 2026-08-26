<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give each parallel worker its own directory for upload parts.
     *
     * Parts are real files under a real path, not a faked disk, and every
     * worker's database restarts session ids at 1 — so two workers writing
     * parts land in the same directory, and ChunkedUploadsTest's afterEach
     * deletes the whole tree for all of them. That showed up as a
     * one-run-in-three failure, which is worse than a steady one: it
     * trains you to re-run rather than look.
     *
     * Outside a parallel run the token is null and everything lands under
     * w0, which is still held apart from whatever a previous run left.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $root = storage_path('app/uploads-tmp/w'.(ParallelTesting::token() ?: '0'));

        config(['projectsend.uploads.parts_path' => $root]);

        // Emptied per test, not just per file. RefreshDatabase rolls back,
        // so session ids restart at 1 in every test — two tests in the
        // same worker reuse the same directory name, and a run that
        // crashed before its cleanup leaves the previous one's parts
        // sitting there under the id the next test is about to claim.
        File::deleteDirectory($root);
    }
}
