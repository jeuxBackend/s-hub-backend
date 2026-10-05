<?php

namespace App\Http\Requests\SchoolAsset;

use App\Enums\SchoolAssetFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_format' => ['sometimes', Rule::in(SchoolAssetFormat::values())],
            'text_value' => ['required_if:asset_format,text', 'nullable', 'string', 'max:500'],
            'file' => ['required_if:asset_format,image', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }
}
