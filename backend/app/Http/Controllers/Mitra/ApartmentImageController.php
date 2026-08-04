<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Apartment\StoreApartmentImageRequest;
use App\Http\Resources\ApartmentImageResource;
use App\Models\Apartment;
use App\Models\ApartmentImage;
use Illuminate\Support\Facades\Storage;

class ApartmentImageController extends Controller
{
    public function store(StoreApartmentImageRequest $request, Apartment $apartment)
    {
        $path = $request->file('image')->store('apartment-images', 'public');

        $image = $apartment->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $apartment->images()->doesntExist(),
            'sort_order' => $apartment->images()->count(),
        ]);

        if ($image->is_primary) {
            $apartment->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new ApartmentImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(Apartment $apartment, ApartmentImage $image)
    {
        $this->authorize('update', $apartment);

        if ($image->apartment_id !== $apartment->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
