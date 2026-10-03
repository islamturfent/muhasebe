<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Base controller providing view rendering and common response helpers.
 */
abstract class Controller
{
    public function view(string $view, array $data = []): Response
    {
        return Response::html(View::instance()->render($view, $data));
    }

    public function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Validate input; throws ValidationException (422) on failure. */
    protected function validate(Request $request, array $rules): array
    {
        $validator = new Validator();
        if (!$validator->validate($request->all(), $rules)) {
            throw new ValidationException($validator->errors());
        }
        return $request->all();
    }
}
