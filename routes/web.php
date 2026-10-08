<?php

use App\Http\Controllers\CateringAttendanceRecapPreviewController;
use App\Http\Controllers\CateringInvoicePreviewController;
use App\Http\Controllers\CateringMonthlyReportPreviewController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/school-classes', fn () => view('school-classes.index'))->name('school-classes.index');
    Route::get('/catering-categories', fn () => view('catering-categories.index'))->name('catering-categories.index');
    Route::get('/catering-members', fn () => view('catering-members.index'))->name('catering-members.index');
    Route::get('/catering-members/import', fn () => view('catering-members.import'))->name('catering-members.import');
    Route::get('/catering-bills', fn () => view('catering-bills.index'))->name('catering-bills.index');
    Route::get('/catering-monthly-report', fn () => view('catering-monthly-report.index'))->name('catering-monthly-report.index');
    Route::get('/catering-monthly-report/preview', CateringMonthlyReportPreviewController::class)
        ->name('catering-monthly-report.preview');
});

Route::middleware('auth')->group(function () {
    Route::get('/catering-attendance', fn () => view('catering-attendance.index'))->name('catering-attendance.index');
    Route::get('/catering-attendance-recap', fn () => view('catering-attendance-recap.index'))->name('catering-attendance-recap.index');
    Route::get('/catering-attendance-recap/preview', CateringAttendanceRecapPreviewController::class)
        ->name('catering-attendance-recap.preview');
    Route::get('/catering-attendance/invoices/member/{member}', [CateringInvoicePreviewController::class, 'member'])
        ->name('catering-attendance.invoice.member');
    Route::get('/catering-attendance/invoices/class/{schoolClass}', [CateringInvoicePreviewController::class, 'classSummary'])
        ->name('catering-attendance.invoice.class');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
