<?php

namespace App\Services\Finance;

use Illuminate\Http\Request;

final class V2OriginalVoucherInput
{
    public static function settlement(Request $request): array
    {
        return $request->validate([
            'passing_status' => ['required', 'integer', 'in:1,2,3'],
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'bank_post_at' => ['required_if:passing_status,1', 'nullable', 'date_format:Y-m-d'],
            'note' => ['required', 'string', 'max:1000'],
            'posting_lines' => ['required_if:passing_status,1', 'array', 'min:2', 'max:100'],
            'posting_lines.*.account_id' => ['required', 'integer', 'min:1'],
            'posting_lines.*.debit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
            'posting_lines.*.credit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
        ]);
    }

    public static function read(Request $request): array
    {
        return $request->validate([
            'request_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/D'],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^[0-9,]{1,14}(?:\.[0-9]{1,2})?$/D'],
            'detail' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['required', 'integer', 'in:0,1,2,3'],
            't_number' => ['required_if:payment_type,2,3', 'nullable', 'string', 'max:255'],
            'bank_id' => ['required_if:payment_type,2,3', 'nullable', 'integer', 'min:1'],
            'passing_date' => ['required_if:payment_type,2,3', 'nullable', 'date_format:Y-m-d'],
            'posting_lines' => ['required_unless:payment_type,2,3', 'array', 'min:2', 'max:100'],
            'posting_lines.*.account_id' => ['required', 'integer', 'min:1'],
            'posting_lines.*.debit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
            'posting_lines.*.credit' => ['required', 'regex:/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/D'],
        ]);
    }

    public static function source(array $data): array
    {
        return ['date' => $data['date'], 'amount' => $data['amount'], 'description' => $data['detail']]
            + array_intersect_key($data, array_flip(['reference', 'payment_type', 't_number', 'bank_id', 'passing_date']));
    }
}
