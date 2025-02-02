Generate data for the requested schema.
Make the results realistic and relevant to the schema.

@if (!empty($seed))
    Here is some preset data:
    @foreach ($seed as $key => $value)
        {{ $key }}: {{ print_r($value, true) }}
    @endforeach
@endif
