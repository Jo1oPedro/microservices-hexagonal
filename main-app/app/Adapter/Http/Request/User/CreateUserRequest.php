<?php

declare(strict_types=1);

namespace App\Adapter\Http\Request\User;

use Hyperf\Validation\Request\FormRequest;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            "name" => "required|string",
            "email" => "required|email",
            "password" => "required|string|min:8",
        ];
    }

    public function messages(): array
    {
        return [
            "name.required" => "Name is required",
            "email.required" => "Email is required",
            "password.required" => "Password is required",
            "email.unique" => "Email already exists",
        ];
    }
}
