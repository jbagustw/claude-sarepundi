<?php

use App\Http\Controllers\Admin\ApartmentModerationController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GatheringVenueModerationController;
use App\Http\Controllers\Admin\GlampingModerationController;
use App\Http\Controllers\Admin\HomestayModerationController;
use App\Http\Controllers\Admin\MitraModerationController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Admin\TransportModerationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VillaModerationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingDocumentController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\Mitra\ApartmentController as MitraApartmentController;
use App\Http\Controllers\Mitra\ApartmentImageController;
use App\Http\Controllers\Mitra\BookingController as MitraBookingController;
use App\Http\Controllers\Mitra\DashboardController as MitraDashboardController;
use App\Http\Controllers\Mitra\GatheringVenueController as MitraGatheringVenueController;
use App\Http\Controllers\Mitra\GatheringVenueImageController;
use App\Http\Controllers\Mitra\GatheringVenueSlotController;
use App\Http\Controllers\Mitra\GlampingController as MitraGlampingController;
use App\Http\Controllers\Mitra\GlampingImageController;
use App\Http\Controllers\Mitra\HomestayController as MitraHomestayController;
use App\Http\Controllers\Mitra\HomestayImageController;
use App\Http\Controllers\Mitra\PayoutController as MitraPayoutController;
use App\Http\Controllers\Mitra\ProfileController as MitraProfileController;
use App\Http\Controllers\Mitra\ReviewController as MitraReviewController;
use App\Http\Controllers\Mitra\TransportController as MitraTransportController;
use App\Http\Controllers\Mitra\TransportImageController;
use App\Http\Controllers\Mitra\VillaAvailabilityController as MitraVillaAvailabilityController;
use App\Http\Controllers\Mitra\VillaController as MitraVillaController;
use App\Http\Controllers\Mitra\VillaImageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Public\ApartmentAvailabilityController as PublicApartmentAvailabilityController;
use App\Http\Controllers\Public\ApartmentController as PublicApartmentController;
use App\Http\Controllers\Public\ApartmentReviewController as PublicApartmentReviewController;
use App\Http\Controllers\Public\ArticleController as PublicArticleController;
use App\Http\Controllers\Public\BannerController as PublicBannerController;
use App\Http\Controllers\Public\CouponController as PublicCouponController;
use App\Http\Controllers\Public\GatheringVenueAvailabilityController as PublicGatheringVenueAvailabilityController;
use App\Http\Controllers\Public\GatheringVenueController as PublicGatheringVenueController;
use App\Http\Controllers\Public\GatheringVenueReviewController as PublicGatheringVenueReviewController;
use App\Http\Controllers\Public\GlampingAvailabilityController as PublicGlampingAvailabilityController;
use App\Http\Controllers\Public\GlampingController as PublicGlampingController;
use App\Http\Controllers\Public\GlampingReviewController as PublicGlampingReviewController;
use App\Http\Controllers\Public\HomestayAvailabilityController as PublicHomestayAvailabilityController;
use App\Http\Controllers\Public\HomestayController as PublicHomestayController;
use App\Http\Controllers\Public\HomestayReviewController as PublicHomestayReviewController;
use App\Http\Controllers\Public\NewsletterController as PublicNewsletterController;
use App\Http\Controllers\Public\ReviewController as PublicReviewController;
use App\Http\Controllers\Public\SiteSettingController as PublicSiteSettingController;
use App\Http\Controllers\Public\TransportAvailabilityController as PublicTransportAvailabilityController;
use App\Http\Controllers\Public\TransportController as PublicTransportController;
use App\Http\Controllers\Public\TransportReviewController as PublicTransportReviewController;
use App\Http\Controllers\Public\VillaAvailabilityController as PublicVillaAvailabilityController;
use App\Http\Controllers\Public\VillaController as PublicVillaController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/villas', [PublicVillaController::class, 'index']);
Route::get('/villas/{slug}', [PublicVillaController::class, 'show']);
Route::get('/villas/{slug}/availability', PublicVillaAvailabilityController::class);
Route::get('/villas/{slug}/reviews', [PublicReviewController::class, 'index']);
Route::get('/articles', [PublicArticleController::class, 'index']);
Route::get('/articles/{slug}', [PublicArticleController::class, 'show']);
Route::get('/coupons', [PublicCouponController::class, 'index']);
Route::get('/banners', [PublicBannerController::class, 'index']);
Route::get('/site-settings', [PublicSiteSettingController::class, 'show']);
Route::post('/newsletter/subscribe', [PublicNewsletterController::class, 'subscribe']);
Route::get('/glampings', [PublicGlampingController::class, 'index']);
Route::get('/glampings/{slug}', [PublicGlampingController::class, 'show']);
Route::get('/glampings/{slug}/availability', PublicGlampingAvailabilityController::class);
Route::get('/glampings/{slug}/reviews', [PublicGlampingReviewController::class, 'index']);
Route::get('/homestays', [PublicHomestayController::class, 'index']);
Route::get('/homestays/{slug}', [PublicHomestayController::class, 'show']);
Route::get('/homestays/{slug}/availability', PublicHomestayAvailabilityController::class);
Route::get('/homestays/{slug}/reviews', [PublicHomestayReviewController::class, 'index']);
Route::get('/apartments', [PublicApartmentController::class, 'index']);
Route::get('/apartments/{slug}', [PublicApartmentController::class, 'show']);
Route::get('/apartments/{slug}/availability', PublicApartmentAvailabilityController::class);
Route::get('/apartments/{slug}/reviews', [PublicApartmentReviewController::class, 'index']);
Route::get('/gathering-venues', [PublicGatheringVenueController::class, 'index']);
Route::get('/gathering-venues/{slug}', [PublicGatheringVenueController::class, 'show']);
Route::get('/gathering-venues/{slug}/availability', PublicGatheringVenueAvailabilityController::class);
Route::get('/gathering-venues/{slug}/reviews', [PublicGatheringVenueReviewController::class, 'index']);
Route::get('/transports', [PublicTransportController::class, 'index']);
Route::get('/transports/{slug}', [PublicTransportController::class, 'show']);
Route::get('/transports/{slug}/availability', PublicTransportAvailabilityController::class);
Route::get('/transports/{slug}/reviews', [PublicTransportReviewController::class, 'index']);

Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Shared across user/mitra/admin — access is gated per-role inside
    // BookingPolicy::viewDocument(), not by route middleware, since all
    // three roles can legitimately need the same booking's documents.
    Route::get('/bookings/{booking}/voucher', [BookingDocumentController::class, 'voucher']);
    Route::get('/bookings/{booking}/receipt', [BookingDocumentController::class, 'receipt']);

    Route::middleware('role:user')->group(function () {
        Route::get('/user/ping', fn () => response()->json(['message' => 'pong from user area']));

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/pay', [PaymentController::class, 'store']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/review', [ReviewController::class, 'store']);
    });

    Route::middleware('role:mitra')->prefix('mitra')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from mitra area']));

        Route::get('/stats', [MitraDashboardController::class, 'stats']);

        Route::get('/profile', [MitraProfileController::class, 'show']);
        Route::patch('/profile', [MitraProfileController::class, 'update']);

        Route::get('/payouts', [MitraPayoutController::class, 'index']);

        Route::apiResource('villas', MitraVillaController::class);
        Route::post('/villas/{villa}/submit', [MitraVillaController::class, 'submit']);
        Route::post('/villas/{villa}/images', [VillaImageController::class, 'store']);
        Route::delete('/villas/{villa}/images/{image}', [VillaImageController::class, 'destroy']);

        Route::get('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'index']);
        Route::put('/villas/{villa}/availability', [MitraVillaAvailabilityController::class, 'update']);

        Route::apiResource('glampings', MitraGlampingController::class);
        Route::post('/glampings/{glamping}/submit', [MitraGlampingController::class, 'submit']);
        Route::post('/glampings/{glamping}/images', [GlampingImageController::class, 'store']);
        Route::delete('/glampings/{glamping}/images/{image}', [GlampingImageController::class, 'destroy']);

        Route::apiResource('homestays', MitraHomestayController::class);
        Route::post('/homestays/{homestay}/submit', [MitraHomestayController::class, 'submit']);
        Route::post('/homestays/{homestay}/images', [HomestayImageController::class, 'store']);
        Route::delete('/homestays/{homestay}/images/{image}', [HomestayImageController::class, 'destroy']);

        Route::apiResource('apartments', MitraApartmentController::class);
        Route::post('/apartments/{apartment}/submit', [MitraApartmentController::class, 'submit']);
        Route::post('/apartments/{apartment}/images', [ApartmentImageController::class, 'store']);
        Route::delete('/apartments/{apartment}/images/{image}', [ApartmentImageController::class, 'destroy']);

        Route::apiResource('gathering-venues', MitraGatheringVenueController::class)
            ->parameters(['gathering-venues' => 'gatheringVenue']);
        Route::post('/gathering-venues/{gatheringVenue}/submit', [MitraGatheringVenueController::class, 'submit']);
        Route::post('/gathering-venues/{gatheringVenue}/images', [GatheringVenueImageController::class, 'store']);
        Route::delete('/gathering-venues/{gatheringVenue}/images/{image}', [GatheringVenueImageController::class, 'destroy']);
        Route::post('/gathering-venues/{gatheringVenue}/slots', [GatheringVenueSlotController::class, 'store']);
        Route::patch('/gathering-venues/{gatheringVenue}/slots/{slot}', [GatheringVenueSlotController::class, 'update']);
        Route::delete('/gathering-venues/{gatheringVenue}/slots/{slot}', [GatheringVenueSlotController::class, 'destroy']);

        Route::apiResource('transports', MitraTransportController::class);
        Route::post('/transports/{transport}/submit', [MitraTransportController::class, 'submit']);
        Route::post('/transports/{transport}/images', [TransportImageController::class, 'store']);
        Route::delete('/transports/{transport}/images/{image}', [TransportImageController::class, 'destroy']);

        Route::get('/bookings', [MitraBookingController::class, 'index']);

        Route::get('/reviews', [MitraReviewController::class, 'index']);
        Route::post('/reviews/{review}/reply', [MitraReviewController::class, 'reply']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong from admin area']));

        Route::get('/villas', [VillaModerationController::class, 'index']);
        Route::post('/villas/{villa}/approve', [VillaModerationController::class, 'approve']);
        Route::post('/villas/{villa}/reject', [VillaModerationController::class, 'reject']);

        Route::get('/glampings', [GlampingModerationController::class, 'index']);
        Route::post('/glampings/{glamping}/approve', [GlampingModerationController::class, 'approve']);
        Route::post('/glampings/{glamping}/reject', [GlampingModerationController::class, 'reject']);

        Route::get('/homestays', [HomestayModerationController::class, 'index']);
        Route::post('/homestays/{homestay}/approve', [HomestayModerationController::class, 'approve']);
        Route::post('/homestays/{homestay}/reject', [HomestayModerationController::class, 'reject']);

        Route::get('/apartments', [ApartmentModerationController::class, 'index']);
        Route::post('/apartments/{apartment}/approve', [ApartmentModerationController::class, 'approve']);
        Route::post('/apartments/{apartment}/reject', [ApartmentModerationController::class, 'reject']);

        Route::get('/gathering-venues', [GatheringVenueModerationController::class, 'index']);
        Route::post('/gathering-venues/{gatheringVenue}/approve', [GatheringVenueModerationController::class, 'approve']);
        Route::post('/gathering-venues/{gatheringVenue}/reject', [GatheringVenueModerationController::class, 'reject']);

        Route::get('/transports', [TransportModerationController::class, 'index']);
        Route::post('/transports/{transport}/approve', [TransportModerationController::class, 'approve']);
        Route::post('/transports/{transport}/reject', [TransportModerationController::class, 'reject']);

        Route::get('/mitras', [MitraModerationController::class, 'index']);
        Route::post('/mitras/{mitra}/approve', [MitraModerationController::class, 'approve']);
        Route::post('/mitras/{mitra}/reject', [MitraModerationController::class, 'reject']);
        Route::patch('/mitras/{mitra}/commission', [MitraModerationController::class, 'updateCommission']);

        Route::get('/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/bookings', [AdminBookingController::class, 'index']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);

        Route::get('/payouts', [AdminPayoutController::class, 'index']);
        Route::post('/payouts/run', [AdminPayoutController::class, 'run']);
        Route::post('/payouts/{payout}/retry', [AdminPayoutController::class, 'retry']);

        Route::get('/articles', [AdminArticleController::class, 'index']);
        Route::post('/articles', [AdminArticleController::class, 'store']);
        Route::get('/articles/{article}', [AdminArticleController::class, 'show']);
        Route::patch('/articles/{article}', [AdminArticleController::class, 'update']);
        Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy']);
        Route::post('/articles/{article}/publish', [AdminArticleController::class, 'publish']);
        Route::post('/articles/{article}/unpublish', [AdminArticleController::class, 'unpublish']);
        Route::post('/articles/{article}/cover', [AdminArticleController::class, 'uploadCover']);

        Route::get('/coupons', [AdminCouponController::class, 'index']);
        Route::post('/coupons', [AdminCouponController::class, 'store']);
        Route::get('/coupons/{coupon}', [AdminCouponController::class, 'show']);
        Route::patch('/coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);

        Route::get('/banners', [AdminBannerController::class, 'index']);
        Route::post('/banners', [AdminBannerController::class, 'store']);
        Route::get('/banners/{banner}', [AdminBannerController::class, 'show']);
        Route::patch('/banners/{banner}', [AdminBannerController::class, 'update']);
        Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy']);
        Route::post('/banners/{banner}/image', [AdminBannerController::class, 'uploadImage']);

        Route::get('/site-settings', [AdminSiteSettingController::class, 'show']);
        Route::patch('/site-settings', [AdminSiteSettingController::class, 'update']);
        Route::post('/site-settings/hero-image', [AdminSiteSettingController::class, 'uploadHeroImage']);
        Route::delete('/site-settings/hero-image', [AdminSiteSettingController::class, 'destroyHeroImage']);
        Route::post('/site-settings/logo', [AdminSiteSettingController::class, 'uploadLogo']);
        Route::delete('/site-settings/logo', [AdminSiteSettingController::class, 'destroyLogo']);
        Route::post('/site-settings/favicon', [AdminSiteSettingController::class, 'uploadFavicon']);
        Route::delete('/site-settings/favicon', [AdminSiteSettingController::class, 'destroyFavicon']);
    });
});
