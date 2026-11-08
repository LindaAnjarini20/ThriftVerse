@if(session('success'))<div class="mb-4 rounded-xl bg-brand-light px-4 py-3 text-sm font-semibold text-brand-dark">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm font-semibold text-red-800">{{ session('error') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
