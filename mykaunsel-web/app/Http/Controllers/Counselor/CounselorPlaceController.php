<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorPlaces;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * "My places" on the counselor's Profile: where an independent counselor sees clients in person. Only the counselor's own places; someone
 * else's is a 404. The rules are in CounselorPlaces.
 */
class CounselorPlaceController extends Controller
{
    public function store(Request $request, CounselorPlaces $places): RedirectResponse
    {
        try {
            $places->add($request->user(), $this->input($request));
        } catch (RuntimeException $e) {
            return $this->back()->withInput()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->back()->with('status', __('Place added. You can now open in-person times there.'));
    }

    public function update(Request $request, int $id, CounselorPlaces $places): RedirectResponse
    {
        try {
            $changed = $places->update($request->user(), $id, $this->input($request));
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return $this->back()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->back()->with('status', $changed === [] ? __('Nothing changed.') : __('Place saved.'));
    }

    public function destroy(Request $request, int $id, CounselorPlaces $places): RedirectResponse
    {
        try {
            $archived = $places->remove($request->user(), $id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return $this->back()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->back()->with('status', $archived
            ? __('Place removed. It stays in the history of your past sessions but is not offered for new times.')
            : __('Place removed.'));
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', Rule::in(config('counselors.profile.states'))],
            'postcode' => ['required', 'regex:/^\d{5}$/'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'postcode.regex' => __('The postcode is five digits.'),
        ]);
    }

    private function back(): RedirectResponse
    {
        return redirect()->to(route('counselor.profile').'#my-places');
    }
}
