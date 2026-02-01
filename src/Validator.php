<?php

namespace App;

class Validator
{
    private const MAX_NICKNAME_LEN = 10;
    private const MAX_EMAIL_LEN = 30;

    /**
     * @param array<string,string> $user
     * @return array<string,string>
     */
    public function validate(array $user): array
    {
        $errors = [];
        if (mb_strlen($user['nickname']) > self::MAX_NICKNAME_LEN) {
            $errors['nickname'] = 'Max nickname length is 10' . self::MAX_NICKNAME_LEN;
        }
        if (mb_strlen($user['email']) > self::MAX_EMAIL_LEN) {
            $errors['email'] = "Max email name length is " . self::MAX_EMAIL_LEN;
        }
        return $errors;
    }
}