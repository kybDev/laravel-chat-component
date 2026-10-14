<?php

namespace Vendor\LaravelChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer'],
            'body' => ['nullable', 'string', 'max:10000'],
            'type' => ['nullable', 'in:text,image,file'],
        ];
    }
}
