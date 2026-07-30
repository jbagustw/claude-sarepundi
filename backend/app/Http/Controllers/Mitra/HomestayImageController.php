<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homestay\StoreHomestayImageRequest;
use App\Http\Resources\HomestayImageResource;
use App\Models\Homestay;
use App\Models\HomestayImage;
use Illuminate\Support\Facades\Storage;

class HomestayImageController extends Controller
{
    public function store(StoreHomestayImageRequest $request, Homestay $homestay)
    {
        $path = $request->file('image')->store('homestay-images', 'public');

        $image = $homestay->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $homestay->images()->doesntExist(),
            'sort_order' => $homestay->images()->count(),
        ]);

        if ($image->is_primary) {
            $homestay->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new HomestayImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(Homestay $homestay, HomestayImage $image)
    {
        $this->authorize('update', $homestay);

        if ($image->homestay_id !== $homestay->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
