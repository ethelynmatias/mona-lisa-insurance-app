<?php

namespace App\QueryBuilders;

use App\Models\AppLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AppLogQueryBuilder
{
    public function __construct(private readonly Request $request) {}

    public function build(): Builder
    {
        return AppLog::query()
            ->orderBy('logged_at', 'desc')
            ->when($this->request->filled('level'),   fn ($q) => $q->level($this->request->level))
            ->when($this->request->filled('channel'), fn ($q) => $q->channel($this->request->channel))
            ->when($this->request->filled('form_id'), fn ($q) => $q->formId($this->request->form_id))
            ->when($this->request->filled('hours'),   fn ($q) => $q->recent((int) $this->request->hours))
            ->when($this->request->filled('search'),  fn ($q) => $q->where('message', 'like', '%' . $this->request->search . '%'));
    }
}
