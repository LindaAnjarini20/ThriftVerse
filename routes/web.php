<?php

use App\Http\Controllers\Admin\{AdminController, VoucherAdminController};
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\{BarterController, CartController, CatalogController, ChatController, CodController, ListingController, NegotiationController, NotificationController, OrderController, ProfileController, VoucherController, WishlistController};
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ---------- Publik ----------
Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/listings/{listing:slug}', [ListingController::class, 'show'])->name('listings.show');

// ---------- Tamu ----------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::post('register', [AuthController::class, 'register'])->name('register.store');
    Route::get('register', fn () => redirect()->route('login', ['tab' => 'register']))->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('verify-email', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', function (EmailVerificationRequest $r) { $r->fulfill(); return redirect()->route('dashboard'); })->middleware('signed')->name('verification.verify');
    Route::post('email/verification-notification', function (Request $r) { $r->user()->sendEmailVerificationNotification(); return back()->with('success', 'Tautan verifikasi dikirim.'); })->middleware('throttle:6,1')->name('verification.send');
});

Route::get('/dashboard', fn () => auth()->check() && auth()->user()->isAdmin() ? redirect()->route('admin.dashboard') : redirect()->route('home'))->middleware('auth')->name('dashboard');

// ---------- Pengguna login (verifikasi email opsional lewat THRIFTVERSE_REQUIRE_VERIFIED) ----------
Route::middleware(array_filter(['auth', config('thriftverse.require_verified') ? 'verified' : null]))->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'password'])->name('password.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Listing penjual
    Route::get('/jual', [ListingController::class, 'mine'])->name('listings.mine');
    Route::get('/jual/baru', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/jual', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/jual/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/jual/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::delete('/jual/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('/listings/{listing}/report', [ListingController::class, 'report'])->name('listings.report');
    Route::post('/listings/{listing}/wishlist', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    // Negosiasi & tawar
    Route::get('/negosiasi', [NegotiationController::class, 'index'])->name('negotiations.index');
    Route::post('/listings/{listing}/bids', [NegotiationController::class, 'store'])->name('bids.store');
    Route::post('/bids/{bid}/respond', [NegotiationController::class, 'respond'])->name('bids.respond');

    // Barter
    Route::get('/barter', [BarterController::class, 'index'])->name('barters.index');
    Route::get('/listings/{listing}/barter', [BarterController::class, 'create'])->name('barters.create');
    Route::post('/listings/{listing}/barter', [BarterController::class, 'store'])->name('barters.store');
    Route::get('/barters/{barter}', [BarterController::class, 'show'])->name('barters.show');
    Route::post('/barters/{barter}/respond', [BarterController::class, 'respond'])->name('barters.respond');

    // Pesanan
    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/listings/{listing}/beli', [OrderController::class, 'buyNow'])->name('orders.buy');
    Route::get('/pesanan/{transaction}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/pesanan/{transaction}/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
    Route::post('/pesanan/{transaction}/checkout', [OrderController::class, 'confirmCheckout'])->name('orders.checkout.confirm');
    Route::post('/pesanan/{transaction}/bayar', [OrderController::class, 'pay'])->name('orders.pay');
    Route::post('/pesanan/{transaction}/kirim', [OrderController::class, 'ship'])->name('orders.ship');
    Route::post('/pesanan/{transaction}/terima', [OrderController::class, 'receive'])->name('orders.receive');
    Route::post('/pesanan/{transaction}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/pesanan/{transaction}/komplain', [OrderController::class, 'dispute'])->name('orders.dispute');
    Route::post('/pesanan/{transaction}/ulasan', [OrderController::class, 'review'])->name('orders.review');

    // Keranjang multi-item
    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::get('/keranjang/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
    Route::post('/keranjang/checkout', [CartController::class, 'place'])->name('cart.place');
    Route::post('/keranjang/bayar/{group}', [CartController::class, 'payAll'])->name('cart.payall');
    Route::post('/keranjang/{listing}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/keranjang/{item}', [CartController::class, 'remove'])->name('cart.remove');

    // Voucher (pratinjau di checkout)
    Route::get('/voucher/cek', [VoucherController::class, 'check'])->name('vouchers.check');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/listings/{listing}/chat', [ChatController::class, 'start'])->name('chat.start');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}', [ChatController::class, 'store'])->name('chat.store');
    Route::get('/chat/{conversation}/pesan', [ChatController::class, 'poll'])->name('chat.poll');

    // COD / ketemuan
    Route::get('/jadwal-cod', [CodController::class, 'index'])->name('cod.index');
    Route::get('/jadwal-cod/{schedule}', [CodController::class, 'show'])->name('cod.show');
    Route::post('/jadwal-cod/{schedule}/usul', [CodController::class, 'propose'])->name('cod.propose');
    Route::post('/jadwal-cod/{schedule}/konfirmasi', [CodController::class, 'confirm'])->name('cod.confirm');
    Route::post('/jadwal-cod/{schedule}/tiba', [CodController::class, 'arrive'])->name('cod.arrive');
    Route::post('/jadwal-cod/{schedule}/serah-terima', [CodController::class, 'handover'])->name('cod.handover');
    Route::post('/jadwal-cod/{schedule}/tolak', [CodController::class, 'reject'])->name('cod.reject');
    Route::post('/jadwal-cod/{schedule}/gagal-bertemu', [CodController::class, 'noShow'])->name('cod.noshow');
    Route::post('/jadwal-cod/{schedule}/masalah', [CodController::class, 'problem'])->name('cod.problem');
});

// ---------- Admin ----------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/listings/{listing}/verify', [AdminController::class, 'verify'])->name('verify');
    Route::get('/sengketa', [AdminController::class, 'disputes'])->name('disputes');
    Route::post('/laporan/{report}/putuskan', [AdminController::class, 'resolve'])->name('resolve');
    Route::get('/voucher', [VoucherAdminController::class, 'index'])->name('vouchers');
    Route::post('/voucher', [VoucherAdminController::class, 'store'])->name('vouchers.store');
    Route::post('/voucher/{voucher}/toggle', [VoucherAdminController::class, 'toggle'])->name('vouchers.toggle');
    Route::get('/pengguna', [AdminController::class, 'users'])->name('users');
    Route::post('/pengguna/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
});
