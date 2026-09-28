<x-filament-panels::page>
    {{-- Show page numbers from 40rem instead of Filament's default 56rem --}}
    <style>
        @container (min-width: 40rem) {
            .frb-view-report .fi-pagination:not(.fi-simple) > .fi-pagination-previous-btn,
            .frb-view-report .fi-pagination:not(.fi-simple) > .fi-pagination-next-btn {
                display: none;
            }

            .frb-view-report .fi-pagination-items {
                display: flex;
            }
        }

        @supports not (container-type: inline-size) {
            @media (min-width: 640px) {
                .frb-view-report .fi-pagination:not(.fi-simple) > .fi-pagination-previous-btn,
                .frb-view-report .fi-pagination:not(.fi-simple) > .fi-pagination-next-btn {
                    display: none;
                }

                .frb-view-report .fi-pagination-items {
                    display: flex;
                }
            }
        }
    </style>

    <div class="frb-view-report">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
