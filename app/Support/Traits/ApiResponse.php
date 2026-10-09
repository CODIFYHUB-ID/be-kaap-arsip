<?php

namespace App\Support\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function success(mixed $data = null, string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? (object) [],
        ], $code);
    }

    /**
     * Return a 201 Created JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a paginated JSON response with meta pagination details.
     *
     * @param mixed $resource
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function paginated(mixed $resource, string $message = 'Data retrieved successfully', int $code = Response::HTTP_OK): JsonResponse
    {
        if (method_exists($resource, 'response')) {
            $response = $resource->response()->getData(true);
            $data = $response['data'] ?? [];
            $meta = $response['meta'] ?? [
                'current_page' => $resource->currentPage(),
                'per_page' => $resource->perPage(),
                'total' => $resource->total(),
                'last_page' => $resource->lastPage(),
            ];
        } else {
            $data = is_array($resource) ? $resource : ($resource->items() ?? []);
            $meta = [
                'current_page' => method_exists($resource, 'currentPage') ? $resource->currentPage() : 1,
                'per_page' => method_exists($resource, 'perPage') ? $resource->perPage() : count($data),
                'total' => method_exists($resource, 'total') ? $resource->total() : count($data),
                'last_page' => method_exists($resource, 'lastPage') ? $resource->lastPage() : 1,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $code
     * @return JsonResponse
     */
    public function error(string $message = 'An error occurred', mixed $errors = null, int $code = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ], $code);
    }

    /**
     * Return an unauthorized JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, null, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, null, Response::HTTP_FORBIDDEN);
    }
}
