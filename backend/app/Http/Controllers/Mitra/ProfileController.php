<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mitra\UpdateProfileRequest;
use App\Http\Resources\MitraProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return new MitraProfileResource($request->user()->mitraProfile->load('user'));
    }

    public function update(UpdateProfileRequest $request)
    {
        $profile = $request->user()->mitraProfile;
        $profile->update($request->validated());

        return new MitraProfileResource($profile->load('user'));
    }
}
