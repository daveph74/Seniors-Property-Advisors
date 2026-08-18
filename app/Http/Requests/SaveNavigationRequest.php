<?php

namespace App\Http\Requests;

use App\Content\Text;
use Illuminate\Foundation\Http\FormRequest;

class SaveNavigationRequest extends FormRequest
{
    /**
     * Wording is stripped before the rules run, so `required` judges what will really be stored.
     * Sanitising afterwards is how "<hr>" passed as a label everywhere else in this CMS until §14.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nav' => array_map(function ($item) {
                $item['label'] = Text::clean($item['label'] ?? null);
                $item['children'] = array_map(
                    fn ($child) => ['label' => Text::clean($child['label'] ?? null)] + (array) $child,
                    $item['children'] ?? [],
                );

                return $item;
            }, (array) $this->input('nav', [])),
            'footer' => [
                'columns' => array_map(fn ($column) => [
                    'heading' => Text::clean($column['heading'] ?? null),
                    'links' => $this->cleanLabels($column['links'] ?? []),
                ], (array) $this->input('footer.columns', [])),
                'links' => $this->cleanLabels((array) $this->input('footer.links', [])),
            ],
        ]);
    }

    private function cleanLabels(array $links): array
    {
        return array_map(fn ($link) => ['label' => Text::clean($link['label'] ?? null)] + (array) $link, $links);
    }

    /** One level of children, and no deeper: the header renders a single dropdown, not a tree. */
    public function rules(): array
    {
        return [
            'nav' => ['present', 'array', 'max:8'],
            'nav.*.label' => ['required', 'string', 'max:80'],
            'nav.*.href' => ['nullable', 'string', 'max:200'],
            'nav.*.children' => ['sometimes', 'array', 'max:8'],
            'nav.*.children.*.label' => ['required', 'string', 'max:80'],
            'nav.*.children.*.href' => ['required', 'string', 'max:200'],

            'footer' => ['present', 'array'],
            'footer.columns' => ['present', 'array', 'max:4'],
            'footer.columns.*.heading' => ['required', 'string', 'max:60'],
            'footer.columns.*.links' => ['present', 'array', 'max:10'],
            'footer.columns.*.links.*.label' => ['required', 'string', 'max:80'],
            'footer.columns.*.links.*.href' => ['nullable', 'string', 'max:200'],

            'footer.links' => ['present', 'array', 'max:6'],
            'footer.links.*.label' => ['required', 'string', 'max:80'],
            'footer.links.*.href' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'nav.*.label.required' => 'Every menu item needs wording.',
            'nav.*.children.*.href.required' => 'An item inside a menu has to lead somewhere.',
            'footer.columns.*.heading.required' => 'Every footer column needs a heading.',
            'nav.max' => 'The header runs out of room past eight items. Group some under a dropdown.',
        ];
    }

    /**
     * A parent with children keeps `href` null on purpose — it is a trigger, not a link, and making
     * it navigable would send people to a page that may not exist.
     */
    public function navLinks(): array
    {
        return array_values(array_map(function (array $item) {
            $children = array_values(array_map(
                fn (array $child) => ['label' => Text::clean($child['label']), 'href' => trim($child['href'])],
                $item['children'] ?? [],
            ));

            $link = ['label' => Text::clean($item['label'])];

            if ($children !== []) {
                return $link + ['href' => null, 'children' => $children];
            }

            return $link + ['href' => $this->href($item['href'] ?? null)];
        }, $this->validated()['nav']));
    }

    public function footerColumns(): array
    {
        return array_values(array_map(fn (array $column) => [
            'heading' => Text::clean($column['heading']),
            'links' => $this->plainLinks($column['links'] ?? []),
        ], $this->validated()['footer']['columns']));
    }

    public function footerLinks(): array
    {
        return $this->plainLinks($this->validated()['footer']['links']);
    }

    private function plainLinks(array $links): array
    {
        return array_values(array_map(fn (array $link) => array_filter([
            'label' => Text::clean($link['label']),
            'href' => $this->href($link['href'] ?? null),
        ], fn ($value) => $value !== null), $links));
    }

    /** Kept as typed, minus surrounding space. A blank one means "not a link yet". */
    private function href(?string $href): ?string
    {
        return trim((string) $href) ?: null;
    }
}
