<?php
namespace App\Http\Controllers;

use App\Models\{Listing, Wishlist};

class WishlistController extends Controller
{
    public function toggle(Listing $listing)
    {
        $w = Wishlist::where('user_id', auth()->id())->where('listing_id', $listing->id)->first();
        $w ? $w->delete() : Wishlist::create(['user_id' => auth()->id(), 'listing_id' => $listing->id]);
        return back();
    }
}
