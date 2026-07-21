<?php

namespace App\Http\Controllers;

use App\Http\Resources\FacilityResource;
use App\Models\Facility;

class FacilityController extends Controller
{
    public function index()
    {
        return FacilityResource::collection(Facility::orderBy('category')->orderBy('name')->get());
    }
}
