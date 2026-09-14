{!! $value === null || $value === '' ? '—' : (new \SeviaFlow\Ui\Support\MoneyFormatter)->formatField($field, $value) !!}
