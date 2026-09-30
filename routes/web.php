<?php

use App\Http\Controllers\Admin\DistributionController;
use App\Http\Controllers\Admin\OilPriceController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PickupController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\PickupController as EmployeePickupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/{page}', [PublicController::class, 'page'])
    ->whereIn('page', ['tentang', 'cara-kerja', 'harga', 'dampak', 'edukasi', 'faq'])
    ->name('public.page');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/lupa-password', [PasswordResetController::class, 'showLinkRequest'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/notifikasi/dibaca', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/akun', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/akun', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/akun/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/setor', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('/setor', [DepositController::class, 'store'])->name('deposits.store');
    Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transaksi/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transaksi/{transaction}/barcode', [BarcodeController::class, 'show'])->name('transactions.barcode');
    Route::post('/transaksi/{transaction}/batal', [TransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::post('/transaksi/{transaction}/sanggah', [TransactionController::class, 'dispute'])->name('transactions.dispute');
});

Route::middleware(['auth', 'employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', EmployeeDashboardController::class)->name('dashboard');
    Route::get('/scan-dropoff', [EmployeePickupController::class, 'scanForm'])->name('scan');
    Route::post('/scan-dropoff', [EmployeePickupController::class, 'scan'])->name('scan.store');
    Route::get('/pickups/available', [EmployeePickupController::class, 'available'])->name('pickups.available');
    Route::post('/pickups/{pickup}/claim', [EmployeePickupController::class, 'claim'])->name('pickups.claim');
    Route::get('/transactions', [EmployeePickupController::class, 'transactions'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [EmployeePickupController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/verify', [EmployeePickupController::class, 'verify'])->name('transactions.verify');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', App\Http\Controllers\Admin\DashboardController::class)->name('dashboard');
    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/verify', [AdminTransactionController::class, 'verify'])->name('transactions.verify');
    Route::post('/transactions/{transaction}/reject', [AdminTransactionController::class, 'reject'])->name('transactions.reject');
    Route::post('/transactions/{transaction}/mark-paid', [AdminTransactionController::class, 'markPaid'])->name('transactions.mark-paid');
    Route::post('/transactions/{transaction}/resolve-dispute', [AdminTransactionController::class, 'resolveDispute'])->name('transactions.resolve-dispute');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::get('/pickups', [PickupController::class, 'index'])->name('pickups.index');
    Route::post('/pickups/{pickup}/assign', [PickupController::class, 'assign'])->name('pickups.assign');
    Route::post('/pickups/{pickup}/unassign', [PickupController::class, 'unassign'])->name('pickups.unassign');
    Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
    Route::get('/prices', [OilPriceController::class, 'index'])->name('prices.index');
    Route::post('/prices', [OilPriceController::class, 'store'])->name('prices.store');
    Route::get('/distributions', [DistributionController::class, 'index'])->name('distributions.index');
    Route::post('/distributions', [DistributionController::class, 'store'])->name('distributions.store');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/employees/{user}', [ReportController::class, 'employee'])->name('reports.employee');
});
