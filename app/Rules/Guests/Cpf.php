<?php

namespace App\Rules\Guests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    /**
     * Accepts the CPF with or without punctuation and checks its two
     * verification digits.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D+/', '', (string) $value);

        if (! self::isValid($cpf)) {
            $fail('Esse CPF não parece válido. Pode conferir os números?');
        }
    }

    public static function isValid(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }

            $digit = ($sum * 10) % 11 % 10;

            if ((int) $cpf[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
