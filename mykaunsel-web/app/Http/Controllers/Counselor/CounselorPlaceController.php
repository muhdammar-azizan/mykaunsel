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
 * "My places": where an independent counselor sees clients in person. The list is on the Profile; adding and changing have their own page with the
 * address and a map to place the pin. Only the counselor's own places; someone else's is a 404. The rules are in CounselorPlaces.
 */
class CounselorPlaceController extends Controller
{
    public function create(Request $request, CounselorPlaces $places)
    {
        if (! $places->hasIndependentPractice($request->user())) {
            return $this->toProfile()->withErrors(['place' => __('Places of your own are for an independent practice.')]);
        }

        if (count($places->list($request->user())) >= CounselorPlaces::max()) {
            return $this->toProfile()->withErrors(['place' => __('You have reached the most places (:max). Remove one you do not use first.', ['max' => CounselorPlaces::max()])]);
        }

        return view('counselors.dashboard.place-form', ['place' => null, 'states' => config('counselors.profile.states')]);
    }

    public function edit(Request $request, int $id, CounselorPlaces $places)
    {
        try {
            $place = $places->find($request->user(), $id);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        if (($why = $places->changeBlock($place)) !== null) {
            return $this->toProfile()->withErrors(['place' => $why]);
        }

        return view('counselors.dashboard.place-form', [
            'place' => [
                'id' => $place->id, 'name' => (string) $place->name, 'address' => (string) $place->address, 'city' => (string) $place->city,
                'state' => (string) $place->state, 'postcode' => (string) $place->postcode, 'description' => (string) $place->description,
                'latitude' => $place->latitude === null ? null : (float) $place->latitude,
                'longitude' => $place->longitude === null ? null : (float) $place->longitude,
            ],
            'states' => config('counselors.profile.states'),
        ]);
    }

    public function store(Request $request, CounselorPlaces $places): RedirectResponse
    {
        $input = $this->input($request);

        try {
            $places->add($request->user(), $input);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->toProfile()->with('status', __('Place added. You can now open in-person times there.'));
    }

    public function update(Request $request, int $id, CounselorPlaces $places): RedirectResponse
    {
        $input = $this->input($request);

        try {
            $changed = $places->update($request->user(), $id, $input);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return $this->toProfile()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->toProfile()->with('status', $changed === [] ? __('Nothing changed.') : __('Place saved.'));
    }

    public function destroy(Request $request, int $id, CounselorPlaces $places): RedirectResponse
    {
        try {
            $archived = $places->remove($request->user(), $id);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return $this->toProfile()->withErrors(['place' => $e->getMessage()]);
        }

        return $this->toProfile()->with('status', $archived
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
            // The pin: required, and inside Malaysia (the form's map keeps it there too).
            'latitude' => ['required', 'numeric', 'between:0.8,7.6'],
            'longitude' => ['required', 'numeric', 'between:99,119.6'],
        ], [
            'postcode.regex' => __('The postcode is five digits.'),
            'latitude.required' => __('Place the pin on the map for this place.'),
            'longitude.required' => __('Place the pin on the map for this place.'),
            'latitude.between' => __('The pin must be inside Malaysia.'),
            'longitude.between' => __('The pin must be inside Malaysia.'),
        ]);
    }

    private function toProfile(): RedirectResponse
    {
        return redirect()->to(route('counselor.profile').'#my-places');
    }
}
