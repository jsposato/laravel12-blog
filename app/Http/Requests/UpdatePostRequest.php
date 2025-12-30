<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by PostPolicy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
            'featured_image' => [
                'nullable',
                'image',
                'mimes:' . implode(',', config('blog.featured_image.allowed_types')),
                'max:' . config('blog.featured_image.max_size'),
            ],
            'published_at' => ['nullable', 'date'],
            'remove_featured_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'featured_image' => 'featured image',
            'published_at' => 'publish date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for your post.',
            'body.required' => 'Please write some content for your post.',
            'body.min' => 'Your post content should be at least :min characters.',
        ];
    }
}
