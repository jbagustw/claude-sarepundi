<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Villa\StoreVillaImageRequest;
use App\Http\Resources\VillaImageResource;
use App\Models\Villa;
use App\Models\VillaImage;
use Illuminate\Support\Facades\Storage;

class VillaImageController extends Controller
{
    public function store(StoreVillaImageRequest $request, Villa $villa)
    {
        $path = $request->file('image')->store('villa-images', 'public');

        $image = $villa->images()->create([
            'image_url' => $path,
            'is_primary' => $request->boolean('is_primary') || $villa->images()->doesntExist(),
            'sort_order' => $villa->images()->count(),
        ]);

        if ($image->is_primary) {
            $villa->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        }

        return (new VillaImageResource($image))->response()->setStatusCode(201);
    }

    public function destroy(Villa $villa, VillaImage $image)
    {
        $this->authorize('update', $villa);

        if ($image->villa_id !== $villa->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json(['message' => 'Foto dihapus.']);
    }
}
