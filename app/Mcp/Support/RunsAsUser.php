<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Shared by the MCP tools: they always act as the person who authorized the AI app, in that person's language, and
 * they turn validation and authorization failures into messages the AI can read and act on.
 */
trait RunsAsUser
{
    /**
     * Runs `$callback` with the authenticated person. Authorization and validation errors become tool errors.
     *
     * @param  Closure(User): Response  $callback
     */
    protected function actAs(Request $request, Closure $callback): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Not authenticated.');
        }

        App::setLocale($user->locale->value);

        try {
            return $callback($user);
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->implode("\n"));
        } catch (AuthorizationException) {
            return Response::error(__('Non hai i permessi per questa operazione.'));
        } catch (ModelNotFoundException) {
            return Response::error(__('Elemento non trovato.'));
        }
    }

    /**
     * Validates the arguments with the very FormRequest the web interface uses (authorization, rules and the extra
     * checks), so a tool can never accept what the interface would refuse.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     * @return T
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    protected function validatedRequest(string $class, array $data, User $user, array $routeParameters = []): FormRequest
    {
        $formRequest = $class::create('/', 'POST', $data);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->setUserResolver(fn (): User => $user);

        $route = new Route('POST', '/', []);
        $route->bind($formRequest);
        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }
        $formRequest->setRouteResolver(fn (): Route => $route);

        $formRequest->validateResolved();

        return $formRequest;
    }
}
