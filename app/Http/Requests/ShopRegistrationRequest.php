<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'gst_number' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'gst_number.regex' => 'Enter a valid 15-character GSTIN.',
            'pincode.digits' => 'Enter a six-digit Indian pincode.',
        ];
    }
}
