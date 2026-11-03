<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\TenantHomeController;
use App\Http\Middleware\SimulationPrivacyHeaders;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Foundation', [
        'phase' => config('cyberaware.phase'),
        'modules' => config('cyberaware.modules'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/invitations/{organisation}/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{organisation}/{token}', [InvitationController::class, 'store'])->middleware('throttle:5,1')->name('invitations.accept');
});

Route::middleware(['auth', 'verified', 'tenant'])->prefix('/org/{organisation}')->group(function () {
    Route::get('/', TenantHomeController::class)->name('tenant.home');
    Route::get('/members', [MembersController::class, 'index'])->middleware('permission:members.manage')->name('tenant.members');
    Route::post('/invitations', [InvitationController::class, 'create'])->middleware('permission:members.manage')->name('tenant.invitations.create');
    Route::get('/learning', [LearningController::class, 'catalogue'])->name('learning.catalogue');
    Route::get('/learning/manage', [LearningController::class, 'admin'])->middleware('permission:learning.manage')->name('learning.admin');
    Route::post('/learning/courses', [LearningController::class, 'store'])->middleware('permission:learning.manage')->name('learning.store');
    Route::post('/learning/courses/{courseId}/versions', [LearningController::class, 'newVersion'])->middleware('permission:learning.manage')->name('learning.versions.create');
    Route::put('/learning/versions/{versionId}/draft', [LearningController::class, 'updateDraft'])->middleware('permission:learning.manage')->name('learning.versions.update');
    Route::post('/learning/versions/{versionId}/publish', [LearningController::class, 'publish'])->middleware('permission:learning.manage')->name('learning.publish');
    Route::post('/learning/assignments', [LearningController::class, 'assign'])->middleware('permission:learning.manage')->name('learning.assign');
    Route::get('/learning/assignments/{assignmentId}/lessons/{lessonId}', [LearningController::class, 'lesson'])->name('learning.lesson');
    Route::get('/learning/assignments/{assignmentId}/lessons/{lessonId}/media', [LearningController::class, 'media'])->name('learning.media');
    Route::post('/learning/assignments/{assignmentId}/lessons/{lessonId}/complete', [LearningController::class, 'completeLesson'])->name('learning.lesson.complete');
    Route::post('/learning/assignments/{assignmentId}/assessment', [LearningController::class, 'submitAssessment'])->middleware('throttle:10,1')->name('learning.assessment.submit');
    Route::get('/simulations/manage', [SimulationController::class, 'manage'])->middleware('permission:simulations.manage')->name('simulations.manage');
    Route::post('/simulations/domains', [SimulationController::class, 'addDomain'])->middleware('permission:simulations.authorise')->name('simulations.domains.add');
    Route::post('/simulations/domains/{domainId}/verify', [SimulationController::class, 'verifyDomain'])->middleware('permission:simulations.authorise')->name('simulations.domains.verify');
    Route::post('/simulations/authorisations', [SimulationController::class, 'authorise'])->middleware('permission:simulations.authorise')->name('simulations.authorise');
    Route::post('/simulations/campaigns', [SimulationController::class, 'createCampaign'])->middleware('permission:simulations.manage')->name('simulations.campaigns.create');
    Route::post('/simulations/campaigns/{campaignId}/approve', [SimulationController::class, 'approve'])->middleware('permission:simulations.approve')->name('simulations.campaigns.approve');
    Route::post('/simulations/campaigns/{campaignId}/schedule', [SimulationController::class, 'schedule'])->middleware('permission:simulations.manage')->name('simulations.campaigns.schedule');
    Route::post('/simulations/campaigns/{campaignId}/cancel', [SimulationController::class, 'cancel'])->middleware('permission:simulations.manage')->name('simulations.campaigns.cancel');
    Route::post('/simulations/settings', [SimulationController::class, 'setEnabled'])->middleware('permission:simulations.kill')->name('simulations.settings');
    Route::post('/simulations/suppressions', [SimulationController::class, 'suppress'])->middleware('permission:simulations.manage')->name('simulations.suppressions');
    Route::post('/simulations/suppressions/{membershipId}/remove', [SimulationController::class, 'unsuppress'])->middleware('permission:simulations.manage')->name('simulations.suppressions.remove');
});

Route::get('/simulation/learn', [SimulationController::class, 'landing'])->middleware(['throttle:60,1', SimulationPrivacyHeaders::class])->name('simulation.landing');
Route::post('/simulation/learn-event', [SimulationController::class, 'event'])->middleware(['throttle:10,1'])->name('simulation.event');
