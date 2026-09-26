<?php

namespace App\Support\Auth;

final class LoginRules
{
    public const EMAIL_DOMAIN = 'gmail.com';

    public const PASSWORD_MIN = 8;

    public const PASSWORD_MAX = 72;

    public const NAME_MIN = 2;

    public const NAME_MAX = 80;

    public const TOKEN_DAYS = 7;

    public const GMAIL_MESSAGE = 'Use a Gmail address ending in @gmail.com.';

    public const PASSWORD_LENGTH_MESSAGE = 'Password must be at least 8 characters.';

    public const PASSWORD_MIX_MESSAGE = 'Password must include at least one letter and one number.';

    public const PASSWORD_MATCH_MESSAGE = 'Password confirmation does not match.';

    public const NAME_MESSAGE = 'Name must be 2 to 80 characters and start with a letter.';

    public const CREDENTIALS_MESSAGE = 'These credentials do not match our records.';

    /**
     * @return list<string>
     */
    public static function email(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'email:rfc',
            'regex:/^[a-z0-9._%+\-]+@gmail\.com$/',
        ];
    }

    /**
     * @return list<string>
     */
    public static function password(bool $confirmed = false): array
    {
        $rules = [
            'required',
            'string',
            'min:'.self::PASSWORD_MIN,
            'max:'.self::PASSWORD_MAX,
            'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    public static function name(): array
    {
        return [
            'required',
            'string',
            'min:'.self::NAME_MIN,
            'max:'.self::NAME_MAX,
            'regex:/^[\p{L}][\p{L}\s\'.\-]{1,79}$/u',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => self::GMAIL_MESSAGE,
            'email.regex' => self::GMAIL_MESSAGE,
            'password.required' => 'Password is required.',
            'password.min' => self::PASSWORD_LENGTH_MESSAGE,
            'password.max' => 'Password must be at most 72 characters.',
            'password.regex' => self::PASSWORD_MIX_MESSAGE,
            'password.confirmed' => self::PASSWORD_MATCH_MESSAGE,
            'password_confirmation.required' => self::PASSWORD_MATCH_MESSAGE,
            'name.required' => 'Name is required.',
            'name.min' => self::NAME_MESSAGE,
            'name.max' => self::NAME_MESSAGE,
            'name.regex' => self::NAME_MESSAGE,
        ];
    }
}
