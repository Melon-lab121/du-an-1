<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(App::instance()->get(View::class)->render($template, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** @param array<string, scalar> $params */
    protected function redirect(string $pathOrRoute, array $params = []): Response
    {
        return Response::redirect(str_starts_with($pathOrRoute, '/') || str_contains($pathOrRoute, '://')
            ? url($pathOrRoute)
            : route($pathOrRoute, $params));
    }

    protected function back(): Response
    {
        return Response::redirect(back_url());
    }

    /**
     * Validate request; nếu lỗi sẽ ném ValidationException (App tự redirect về form kèm lỗi + old input,
     * hoặc trả JSON 422 với request API/AJAX).
     *
     * @param array<string, string|string[]> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        [$data, $errors] = App::instance()->get(Validator::class)->validate($request->all(), $rules, $labels);
        if ($errors !== []) {
            throw new ValidationException($errors, $request->all());
        }
        return $data;
    }

    protected function flash(string $type, string $message): void
    {
        App::instance()->get(Session::class)->flash($type, $message);
    }
}
