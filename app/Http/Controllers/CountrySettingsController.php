<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CountrySettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);

        $search = trim((string) $request->query('q', ''));

        $countries = Country::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('iso_alpha_2', 'like', "%{$search}%")
                        ->orWhere('iso_alpha_3', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Modules/Settings/Countries/Index', [
            'module' => $module,
            'countries' => $countries,
            'filters' => ['q' => $search],
            'canCreate' => $request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.create'),
            'canUpdate' => $request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.update'),
            'canDelete' => $request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.delete'),
        ]);
    }

    public function create(Request $request): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.create'), 403);

        return Inertia::render('Modules/Settings/Countries/Form', [
            'module' => $module,
            'country' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.create'), 403);

        $this->mergeIsoAlpha3($request);

        $data = $request->validate($this->countryRules());

        Country::query()->create([
            'name' => $data['name'],
            'iso_alpha_2' => strtoupper($data['iso_alpha_2']),
            'iso_alpha_3' => $data['iso_alpha_3'] !== null && $data['iso_alpha_3'] !== ''
                ? strtoupper($data['iso_alpha_3'])
                : null,
        ]);

        return redirect()->route('modules.show', 'settings-countries')->with('success', 'País criado com sucesso.');
    }

    public function edit(Request $request, Country $country): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.update'), 403);

        return Inertia::render('Modules/Settings/Countries/Form', [
            'module' => $module,
            'country' => $country,
        ]);
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.update'), 403);

        $this->mergeIsoAlpha3($request);

        $data = $request->validate($this->countryRules($country->id));

        $country->update([
            'name' => $data['name'],
            'iso_alpha_2' => strtoupper($data['iso_alpha_2']),
            'iso_alpha_3' => $data['iso_alpha_3'] !== null && $data['iso_alpha_3'] !== ''
                ? strtoupper($data['iso_alpha_3'])
                : null,
        ]);

        return redirect()->route('modules.show', 'settings-countries')->with('success', 'País atualizado com sucesso.');
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'settings-countries');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.settings-countries.delete'), 403);

        $country->delete();

        return redirect()->route('modules.show', 'settings-countries')->with('success', 'País removido.');
    }

    /** @return array<string, mixed> */
    private function countryRules(?int $ignoreCountryId = null): array
    {
        $iso2Unique = Rule::unique('countries', 'iso_alpha_2');
        $iso3Unique = Rule::unique('countries', 'iso_alpha_3');
        if ($ignoreCountryId !== null) {
            $iso2Unique = $iso2Unique->ignore($ignoreCountryId);
            $iso3Unique = $iso3Unique->ignore($ignoreCountryId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'iso_alpha_2' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/', $iso2Unique],
            'iso_alpha_3' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', $iso3Unique],
        ];
    }

    private function mergeIsoAlpha3(Request $request): void
    {
        $raw = trim((string) $request->input('iso_alpha_3', ''));
        $request->merge([
            'iso_alpha_3' => $raw !== '' ? $raw : null,
        ]);
    }
}
