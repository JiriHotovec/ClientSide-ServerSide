@if($errors->any())
    <div class="status status-error" role="alert"><strong>{{ __('Please fix the following:') }}</strong>
        <ul>
            @foreach($errors->messages() as $field => $messages)
                @foreach($messages as $message)
                    <li><a href="#tab-{{ \App\Support\DeviceFormTabs::tabFor($field) }}" data-goto-tab="{{ \App\Support\DeviceFormTabs::tabFor($field) }}">{{ __(\App\Support\DeviceFormTabs::TABS[\App\Support\DeviceFormTabs::tabFor($field)]) }}</a>: {{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
