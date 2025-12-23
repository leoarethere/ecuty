@php
    use Carbon\Carbon;
    
    // State management
    $statePath = $getStatePath();
    
    // ✅ PERBAIKAN: Handle data existing saat edit
    $selectedDates = [];
    $rawState = $getState();
    
    if (is_array($rawState)) {
        $selectedDates = $rawState;
    } elseif (is_string($rawState)) {
        // Jika data tersimpan sebagai JSON string
        $decoded = json_decode($rawState, true);
        $selectedDates = is_array($decoded) ? $decoded : [];
    }
    
    // Get current month/year or use defaults
    $currentMonth = request()->get('calendar_month', now()->month);
    $currentYear = request()->get('calendar_year', now()->year);
    
    // Generate calendar
    $firstDay = Carbon::create($currentYear, $currentMonth, 1);
    $lastDay = $firstDay->copy()->endOfMonth();
    $daysInMonth = $lastDay->day;
    $startDayOfWeek = $firstDay->dayOfWeek; // 0=Minggu, 1=Senin, ...
    
    // Previous and next month
    $prevMonth = $firstDay->copy()->subMonth();
    $nextMonth = $firstDay->copy()->addMonth();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        selectedDates: @js($selectedDates),
        currentMonth: {{ $currentMonth }},
        currentYear: {{ $currentYear }},
        
        toggleDate(date) {
            const index = this.selectedDates.indexOf(date);
            if (index > -1) {
                this.selectedDates.splice(index, 1);
            } else {
                this.selectedDates.push(date);
            }
            this.selectedDates.sort();
            $wire.set('{{ $statePath }}', this.selectedDates);
            this.updateRangeDisplay();
        },
        
        isSelected(date) {
            return this.selectedDates.includes(date);
        },
        
        changeMonth(delta) {
            let newMonth = this.currentMonth + delta;
            let newYear = this.currentYear;
            
            if (newMonth > 12) {
                newMonth = 1;
                newYear++;
            } else if (newMonth < 1) {
                newMonth = 12;
                newYear--;
            }
            
            // Reload dengan parameter bulan baru
            window.location.href = '{{ request()->url() }}?calendar_month=' + newMonth + '&calendar_year=' + newYear;
        },
        
        updateRangeDisplay() {
            if (this.selectedDates.length > 0) {
                const sorted = [...this.selectedDates].sort();
                const first = sorted[0];
                const last = sorted[sorted.length - 1];
                
                // Update hidden fields untuk kompatibilitas
                $wire.set('data.tanggal_mulai', first);
                $wire.set('data.tanggal_akhir', last);
            }
        }
    }" x-init="updateRangeDisplay()" class="space-y-4">
        
        <!-- Header Calendar -->
        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <button 
                type="button"
                @click="changeMonth(-1)"
                class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                ← Bulan Lalu
            </button>
            
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $firstDay->isoFormat('MMMM Y') }}
            </h3>
            
            <button 
                type="button"
                @click="changeMonth(1)"
                class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600"
            >
                Bulan Depan →
            </button>
        </div>

        <!-- Day Names -->
        <div class="grid grid-cols-7 gap-1 text-center font-medium text-sm text-gray-600 dark:text-gray-400">
            <div class="p-2">Min</div>
            <div class="p-2">Sen</div>
            <div class="p-2">Sel</div>
            <div class="p-2">Rab</div>
            <div class="p-2">Kam</div>
            <div class="p-2">Jum</div>
            <div class="p-2 text-red-600 dark:text-red-400">Sab</div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-1">
            @php
                // Empty cells before first day
                for ($i = 0; $i < $startDayOfWeek; $i++) {
                    echo '<div class="p-2"></div>';
                }
                
                // Days of month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($currentYear, $currentMonth, $day)->format('Y-m-d');
                    $dayOfWeek = Carbon::create($currentYear, $currentMonth, $day)->dayOfWeek;
                    $isWeekend = in_array($dayOfWeek, [0, 6]); // Minggu atau Sabtu
                    $isPast = Carbon::create($currentYear, $currentMonth, $day)->lt(now()->startOfDay());
            @endphp
                    <div>
                        <label 
                            class="flex items-center justify-center p-2 cursor-pointer rounded-lg border-2 transition-all
                                {{ $isWeekend ? 'bg-red-50 dark:bg-red-900/20' : 'bg-white dark:bg-gray-800' }}
                                {{ $isPast ? 'opacity-50 cursor-not-allowed' : 'hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20' }}
                                border-gray-200 dark:border-gray-700"
                            :class="{
                                'border-primary-600 bg-primary-100 dark:bg-primary-900/40 font-bold': isSelected('{{ $date }}')
                            }"
                        >
                            <input 
                                type="checkbox"
                                class="sr-only"
                                value="{{ $date }}"
                                @click="toggleDate('{{ $date }}')"
                                :checked="isSelected('{{ $date }}')"
                                {{ $isPast ? 'disabled' : '' }}
                            >
                            <span 
                                class="text-sm {{ $isWeekend ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}"
                                :class="{ 'font-bold text-primary-700 dark:text-primary-300': isSelected('{{ $date }}') }"
                            >
                                {{ $day }}
                            </span>
                        </label>
                    </div>
            @php
                }
            @endphp
        </div>

        <!-- Summary -->
        <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                    Tanggal Dipilih:
                </span>
                <span class="text-lg font-bold text-blue-700 dark:text-blue-300" x-text="selectedDates.length + ' Hari'">
                    0 Hari
                </span>
            </div>
            
            <div class="mt-2 text-xs text-blue-700 dark:text-blue-300" x-show="selectedDates.length > 0">
                <span x-text="selectedDates.length > 3 
                    ? selectedDates[0] + ' ... ' + selectedDates[selectedDates.length - 1] 
                    : selectedDates.join(', ')">
                </span>
            </div>
        </div>

        <!-- Info -->
        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
            <p>✅ Klik tanggal untuk memilih/membatalkan cuti</p>
            <p>📅 Anda bisa memilih tanggal tidak berurutan (misalnya: 1,2,3 lalu 7,8,9)</p>
        </div>
    </div>
</x-dynamic-component>