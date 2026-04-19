<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Http\Controllers\Admin\AdminAuthController;
use EvilStudio\Cryptosik\Http\Controllers\Admin\AdminAuditLogController;
use EvilStudio\Cryptosik\Http\Controllers\Admin\AdminDashboardController;
use EvilStudio\Cryptosik\Http\Controllers\Admin\AdminUserController;
use EvilStudio\Cryptosik\Http\Controllers\Admin\AdminVaultController;
use EvilStudio\Cryptosik\Http\Controllers\Auth\UserAuthController;
use EvilStudio\Cryptosik\Http\Controllers\LocaleController;
use EvilStudio\Cryptosik\Http\Controllers\User\UserSettingsController;
use EvilStudio\Cryptosik\Http\Controllers\Vault\DraftController;
use EvilStudio\Cryptosik\Http\Controllers\Vault\VaultController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

$adminPath = (string) config('cryptosik.admin.path', 'admin');

Route::get('/', [UserAuthController::class, 'showLogin'])->name('home');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/login', [UserAuthController::class, 'showLogin'])->name('auth.user.login.show');
Route::post('/login/request-code', [UserAuthController::class, 'requestCode'])->name('auth.user.login.request-code');
Route::post('/login/verify-code', [UserAuthController::class, 'verifyCode'])->name('auth.user.login.verify-code');

Route::middleware('cryptosik.user')->group(function (): void {
    Route::get('/vault/unlock', [UserAuthController::class, 'showUnlock'])->name('auth.vault.unlock.show');
    Route::post('/vault/unlock', [UserAuthController::class, 'unlockVault'])->name('auth.vault.unlock.submit');
    Route::post('/logout', [UserAuthController::class, 'logout'])->name('auth.user.logout');
    Route::post('/settings', [UserSettingsController::class, 'update'])->name('user.settings.update');

    Route::middleware('cryptosik.vault')->group(function (): void {
        Route::get('/vault', [VaultController::class, 'workspace'])->name('vault.workspace');
        Route::post('/vault/lock', [VaultController::class, 'lock'])->name('vault.lock');
        Route::post('/vault/description', [VaultController::class, 'updateDescription'])->name('vault.description.update');
        Route::get('/vault/entries/{entry}/attachments/{attachment}', [VaultController::class, 'downloadAttachment'])->name('vault.entries.attachments.download');

        Route::post('/vault/draft/save', [DraftController::class, 'save'])->name('vault.draft.save');
        Route::post('/vault/draft/delete', [DraftController::class, 'delete'])->name('vault.draft.delete');
        Route::post('/vault/draft/finalize', [DraftController::class, 'finalize'])->name('vault.draft.finalize');
        Route::post('/vault/draft/attachments', [DraftController::class, 'uploadAttachment'])->name('vault.draft.attachments.upload');
        Route::post('/vault/draft/attachments/{attachment}/delete', [DraftController::class, 'deleteAttachment'])->name('vault.draft.attachments.delete');
    });
});

Route::prefix($adminPath)->group(function (): void {
    Route::get('/', static fn (): RedirectResponse => redirect()->route('admin.login.show'))->name('admin.root');
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login.show');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');

    Route::middleware('cryptosik.admin')->group(function (): void {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/logs', [AdminAuditLogController::class, 'index'])->name('admin.logs.index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::post('/users/{user}/nickname', [AdminUserController::class, 'updateNickname'])->name('admin.users.nickname.update');
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.users.deactivate');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('admin.users.activate');

        Route::get('/vaults', [AdminVaultController::class, 'index'])->name('admin.vaults.index');
        Route::post('/vaults', [AdminVaultController::class, 'store'])->name('admin.vaults.store');
        Route::post('/vaults/{vault}/members', [AdminVaultController::class, 'assignMember'])->name('admin.vaults.members.assign');
        Route::post('/vaults/{vault}/archive', [AdminVaultController::class, 'archive'])->name('admin.vaults.archive');
        Route::post('/vaults/{vault}/soft-delete', [AdminVaultController::class, 'softDelete'])->name('admin.vaults.soft-delete');
        Route::post('/vaults/{vaultId}/restore', [AdminVaultController::class, 'restore'])->name('admin.vaults.restore');
    });
});
