<?php

namespace App\Utils;

use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class Fail
{
    /** @var array<int, mixed> */
    public array $errors = [];

    /**
     * Throw a validation exception when the condition is true.
     * Usage:
     * Fail::when($branch->stocks()->exists(), [
     *   'price' => __('validation.between.numeric', [
     *      'min' => 1,
     *      'max' => 16000000,
     *    ])
     * ]);
     * We don't need to pass 'attribute', it will automatically use __(array key) as attribute.
     *
     * @param  array<string,string>  $messages
     *
     * @throws ValidationException
     */
    public static function when(bool $condition, array $messages): void
    {
        if ($condition) {
            $validator = validator([], []); // No rules initially
            $validator->after(fn (Validator $validator) => self::validateMessages($validator, $messages));
            throw new ValidationException($validator);
        }
    }

    /**
     * Group multiple validations and throw if any failed.
     * Usage
     *
     * Fail::group(function (Fail $fail) {
     *     $fail->if($branch->stocks()->exists(), [
     *         'price' => __('validation.between.numeric', [
     *             'min' => 1,
     *             'max' => 16000000,
     *         ])
     *     ]);
     *     $fail->if($branch->is_active === false, [
     *         'is_active' => __('validation.accepted'),
     *     ]);
     * });
     */
    public static function group(\Closure $callback): void
    {
        $fail = new self();
        $callback($fail);

        if (count($fail->errors)) {
            $validator = validator([], []);
            $validator->after(function (Validator $validator) use ($fail) {
                foreach ($fail->errors as $messages) {
                    self::validateMessages($validator, $messages);
                }
            });
            throw new ValidationException($validator);
        }
    }

    /**
     * Deferred version for use in group().
     *
     * @param  array<string,string>  $messages
     */
    public function if(bool $condition, $messages): void
    {
        if ($condition) {
            $this->errors[] = $messages;
        }
    }

    /**
     * @param  array<string,string>  $messages
     */
    private static function validateMessages(Validator $validator, array $messages): void
    {
        foreach ($messages as $attribute => $message) {
            $langKey = "validation.attributes.{$attribute}";
            $translatedAttribute = Lang::get($langKey);
            $message = str_replace(
                search: [':attribute', ':Attribute'],
                replace: [$translatedAttribute, ucfirst($translatedAttribute)],
                subject: $message
            );
            $validator->errors()->add($attribute, $message);
        }
    }
}
