<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Glamping\StoreGlampingImageRequest;
use App\Http\Resources\GlampingImageResource;
use App\Models\Glamping;
use App\Models\GlampingImage;
use Illuminate\Support\Facades\Storage;

class GlampingImageController extends Controller
{
    public function store(StoreGlampingImageRequest $request, Glamping $glamping)
    {
        $path = $request->file('image')->store('glamping-images', 'public');

        $image = $glamping->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $glamping->images()->doesntExist(),
            'sort_order' => $glamping->images()->count(),
        ]);

        if ($image->is_primary) {
            $glamping->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new GlampingImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(Glamping $glamping, GlampingImage $image)
    {
        $this->authorize('update', $glamping);

        if ($image->glamping_id !== $glamping->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
