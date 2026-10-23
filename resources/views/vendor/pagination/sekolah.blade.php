@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="pager" role="navigation" aria-label="Halaman">
        <span class="small muted num">
            Menampilkan {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </span>
        @if ($paginator->hasPages())
            <span class="row-actions">
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() ?? '#' }}" rel="prev" aria-disabled="{{ $paginator->onFirstPage() ? 'true' : 'false' }}">Sebelumnya</a>
                <span class="small muted num" style="align-self:center">Hal. {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() ?? '#' }}" rel="next" aria-disabled="{{ $paginator->hasMorePages() ? 'false' : 'true' }}">Berikutnya</a>
            </span>
        @endif
    </nav>
@endif
