<?php
$pageTitle = 'Offers';
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$activeTab = $_GET['status'] ?? 'all';
?>

<?php
$mockOffers = [
    ['name'=>'Alex Morrison','initials'=>'AM','email'=>'alex@email.com','position'=>'Senior Engineer','dept'=>'Engineering','salary'=>'9,500','type'=>'month','status'=>'pending','expires_days'=>5,'benefits'=>true],
    ['name'=>'Sarah Chen','initials'=>'SC','email'=>'sarah@email.com','position'=>'Product Manager','dept'=>'Product','salary'=>'95,000','type'=>'year','status'=>'accepted','expires_days'=>0,'benefits'=>true],
    ['name'=>'Marcus Johnson','initials'=>'MJ','email'=>'marcus@email.com','position'=>'UX Designer','dept'=>'Design','salary'=>'7,200','type'=>'month','status'=>'draft','expires_days'=>0,'benefits'=>false],
    ['name'=>'Priya Patel','initials'=>'PP','email'=>'priya@email.com','position'=>'Data Scientist','dept'=>'Analytics','salary'=>'110,000','type'=>'year','status'=>'pending','expires_days'=>2,'benefits'=>true],
    ['name'=>'James Wilson','initials'=>'JW','email'=>'james@email.com','position'=>'DevOps Engineer','dept'=>'Infrastructure','salary'=>'8,800','type'=>'month','status'=>'declined','expires_days'=>0,'benefits'=>true],
    ['name'=>'Emma Rodriguez','initials'=>'ER','email'=>'emma@email.com','position'=>'Marketing Lead','dept'=>'Marketing','salary'=>'75,000','type'=>'year','status'=>'accepted','expires_days'=>0,'benefits'=>true],
    ['name'=>'David Kim','initials'=>'DK','email'=>'david@email.com','position'=>'Backend Developer','dept'=>'Engineering','salary'=>'8,200','type'=>'month','status'=>'draft','expires_days'=>0,'benefits'=>false],
    ['name'=>'Lisa Thompson','initials'=>'LT','email'=>'lisa@email.com','position'=>'HR Specialist','dept'=>'Human Resources','salary'=>'55,000','type'=>'year','status'=>'pending','expires_days'=>7,'benefits'=>true],
];

$tabCounts = ['all' => 24, 'draft' => 5, 'pending' => 8, 'accepted' => 9, 'declined' => 2];
$tabs      = ['all' => 'All', 'draft' => 'Draft', 'pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined'];

$statusBadge = [
    'draft'    => 'bg-gray-100 text-gray-600',
    'pending'  => 'bg-amber-100 text-amber-700',
    'accepted' => 'bg-green-100 text-green-700',
    'declined' => 'bg-red-100 text-red-700',
];
?>

<!-- Toast container -->
<div id="toastContainer" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

<!-- Page wrapper -->
<div class="p-6 lg:p-8 max-w-screen-xl mx-auto">

    <!-- ───────── PAGE HEADER ───────── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Offers</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage candidate offer letters and compensation packages</p>
        </div>
        <button
            onclick="openCreateModal()"
            class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors shadow-sm self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Create Offer
        </button>
    </div>

    <!-- ───────── TABS ───────── -->
    <div class="flex items-center gap-1 mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 overflow-x-auto">
        <?php foreach ($tabs as $key => $label): ?>
        <?php
            $isActive = ($activeTab === $key);
            $tabCls   = $isActive
                ? 'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 text-white transition-all whitespace-nowrap'
                : 'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all whitespace-nowrap';
            $badgeCls = $isActive
                ? 'bg-violet-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full'
                : 'bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full';
        ?>
        <button class="<?= $tabCls ?>" data-tab="<?= $key ?>" onclick="switchTab('<?= $key ?>')">
            <?= $label ?>
            <span class="<?= $badgeCls ?>"><?= $tabCounts[$key] ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ───────── OFFER CARDS ───────── -->
    <div id="offersContainer" class="flex flex-col gap-4">

        <?php foreach ($mockOffers as $idx => $offer): ?>
        <?php
            $show        = ($activeTab === 'all' || $activeTab === $offer['status']);
            $badge       = $statusBadge[$offer['status']];
            $salaryLabel = '$' . $offer['salary'] . ' / ' . $offer['type'];
            $dotCls      = [
                'draft'    => 'bg-gray-400',
                'pending'  => 'bg-amber-500',
                'accepted' => 'bg-green-500',
                'declined' => 'bg-red-500',
            ][$offer['status']] ?? 'bg-gray-400';
        ?>
        <div
            class="offer-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-5 transition-all hover:shadow-md"
            data-status="<?= $offer['status'] ?>"
            data-id="offer-<?= $idx ?>"
            <?= $show ? '' : 'style="display:none"' ?>>

            <!-- Candidate -->
            <div class="flex items-center gap-3 min-w-[190px]">
                <div class="w-11 h-11 rounded-full bg-violet-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-sm select-none">
                    <?= htmlspecialchars($offer['initials']) ?>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm leading-tight"><?= htmlspecialchars($offer['name']) ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($offer['email']) ?></p>
                </div>
            </div>

            <!-- Position -->
            <div class="min-w-[155px]">
                <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($offer['position']) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($offer['dept']) ?></p>
            </div>

            <!-- Salary -->
            <div class="min-w-[155px]">
                <p class="text-lg font-bold text-amber-500 leading-tight"><?= $salaryLabel ?></p>
                <?php if ($offer['benefits']): ?>
                <p class="text-xs text-gray-400 mt-0.5">+ Benefits package</p>
                <?php else: ?>
                <p class="text-xs text-gray-300 mt-0.5">No benefits</p>
                <?php endif; ?>
            </div>

            <!-- Status + expiry -->
            <div class="flex flex-col gap-1.5 min-w-[130px]">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold w-fit <?= $badge ?>">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?= $dotCls ?>"></span>
                    <?= ucfirst($offer['status']) ?>
                </span>
                <?php if ($offer['status'] === 'pending' && $offer['expires_days'] > 0): ?>
                    <?php $expClr = $offer['expires_days'] <= 3 ? 'text-red-500' : 'text-amber-600'; ?>
                    <span class="text-xs font-medium <?= $expClr ?>">
                        <?= $offer['expires_days'] <= 3 ? '⚠ ' : '' ?>Expires in <?= $offer['expires_days'] ?> day<?= $offer['expires_days'] !== 1 ? 's' : '' ?>
                    </span>
                <?php elseif ($offer['status'] === 'accepted'): ?>
                    <span class="text-xs text-gray-400">Offer accepted</span>
                <?php elseif ($offer['status'] === 'declined'): ?>
                    <span class="text-xs text-gray-400">Offer declined</span>
                <?php else: ?>
                    <span class="text-xs text-gray-400">Not yet sent</span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 flex-wrap ml-auto">
                <?php if ($offer['status'] === 'draft'): ?>
                    <button onclick="openViewModal(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-violet-300 text-violet-600 rounded-full hover:bg-violet-50 transition-colors">Edit</button>
                    <button onclick="confirmSendCard(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium bg-violet-600 hover:bg-violet-700 text-white rounded-full transition-colors">Send</button>
                    <button onclick="deleteOffer(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-red-200 text-red-500 rounded-full hover:bg-red-50 transition-colors">Delete</button>
                <?php elseif ($offer['status'] === 'pending'): ?>
                    <button onclick="openViewModal(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-gray-200 text-gray-600 rounded-full hover:bg-gray-50 transition-colors">View</button>
                    <button onclick="resendOffer(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-violet-300 text-violet-600 rounded-full hover:bg-violet-50 transition-colors">Resend</button>
                    <button onclick="withdrawOffer(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-amber-200 text-amber-600 rounded-full hover:bg-amber-50 transition-colors">Withdraw</button>
                <?php elseif ($offer['status'] === 'accepted'): ?>
                    <button onclick="openViewModal(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-gray-200 text-gray-600 rounded-full hover:bg-gray-50 transition-colors">View Letter</button>
                    <button onclick="downloadPDF(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-gray-200 text-gray-600 rounded-full hover:bg-gray-50 transition-colors">Download PDF</button>
                    <button onclick="markAsHired(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-full transition-colors">Mark as Hired</button>
                <?php elseif ($offer['status'] === 'declined'): ?>
                    <button onclick="openViewModal(<?= $idx ?>)" class="px-3 py-1.5 text-xs font-medium border border-gray-200 text-gray-600 rounded-full hover:bg-gray-50 transition-colors">View</button>
                    <button onclick="openCreateModal()" class="px-3 py-1.5 text-xs font-medium bg-violet-600 hover:bg-violet-700 text-white rounded-full transition-colors">Create New Offer</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Empty state -->
        <div id="emptyState" class="hidden">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="font-semibold text-gray-500">No offers found</p>
                <p class="text-sm text-gray-400 mt-1">Create your first offer to get started</p>
                <button onclick="openCreateModal()" class="mt-4 inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create Offer
                </button>
            </div>
        </div>

    </div><!-- /offersContainer -->
</div><!-- /page wrapper -->


<!-- ══════════════════════════════════════════════════════════
     CREATE OFFER MODAL
══════════════════════════════════════════════════════════ -->
<div id="createOfferModal" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCreateModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col pointer-events-auto">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Create Offer</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Build and send a professional offer letter</p>
                </div>
                <button onclick="closeCreateModal()" aria-label="Close" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Scrollable body -->
            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-7">

                <!-- §1 Candidate -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">1</span>
                        Candidate
                    </h3>

                    <!-- Selected pill -->
                    <div id="selectedCandidatePill" class="hidden mb-2">
                        <div class="inline-flex items-center gap-2 bg-violet-50 border border-violet-200 text-violet-700 px-3 py-1.5 rounded-full text-sm font-medium">
                            <span class="w-5 h-5 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold flex-shrink-0" id="selectedInitials"></span>
                            <span id="selectedName"></span>
                            <button onclick="clearCandidate()" aria-label="Remove candidate" class="ml-1 w-4 h-4 rounded-full hover:bg-violet-200 flex items-center justify-center transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Search wrapper -->
                    <div id="searchWrapper" class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            id="candidateSearch"
                            autocomplete="off"
                            placeholder="Search candidates in final review..."
                            oninput="handleCandidateSearch(this.value)"
                            class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow">
                        <!-- Dropdown -->
                        <div id="candidateDropdown" class="hidden absolute z-30 w-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl overflow-hidden"></div>
                    </div>
                </div>

                <!-- §2 Position Details -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">2</span>
                        Position Details
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Position Title</label>
                            <input type="text" id="offerPosition" placeholder="e.g. Senior Software Engineer"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Start Date</label>
                            <input type="date" id="offerStartDate"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Offer Expiry Date</label>
                            <input type="date" id="offerExpiryDate"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow">
                        </div>
                    </div>
                </div>

                <!-- §3 Compensation -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">3</span>
                        Compensation
                    </h3>
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Currency</label>
                            <select id="offerCurrency" onchange="updateCurrencySymbol()"
                                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-white transition-shadow">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                                <option value="CAD">CAD</option>
                                <option value="AUD">AUD</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Salary Amount</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium select-none" id="currencySymbol">$</span>
                                <input type="text" id="offerSalary" placeholder="0" oninput="formatSalaryInput(this)"
                                    class="w-full pl-7 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow">
                            </div>
                        </div>
                    </div>
                    <!-- Salary type toggle -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Salary Type</label>
                        <div class="inline-flex border border-gray-200 rounded-xl p-0.5 bg-gray-50">
                            <button id="typeMonthly" type="button" onclick="setSalaryType('monthly')"
                                class="px-5 py-2 text-sm font-semibold rounded-lg transition-all bg-white shadow-sm text-violet-700 border border-violet-200">
                                Monthly
                            </button>
                            <button id="typeAnnual" type="button" onclick="setSalaryType('annual')"
                                class="px-5 py-2 text-sm font-medium rounded-lg transition-all text-gray-500 hover:text-gray-700">
                                Annual
                            </button>
                        </div>
                    </div>
                </div>

                <!-- §4 Benefits -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">4</span>
                        Benefits
                    </h3>
                    <div class="grid grid-cols-2 gap-2">
                        <?php
                        $benefits = [
                            ['id' => 'ben_health', 'label' => 'Health Insurance',       'icon' => '❤️'],
                            ['id' => 'ben_dental', 'label' => 'Dental Coverage',         'icon' => '🦷'],
                            ['id' => 'ben_vision', 'label' => 'Vision Coverage',         'icon' => '👓'],
                            ['id' => 'ben_remote', 'label' => 'Remote Work',             'icon' => '🏠'],
                            ['id' => 'ben_stock',  'label' => 'Stock Options',           'icon' => '📈'],
                            ['id' => 'ben_bonus',  'label' => 'Annual Bonus',            'icon' => '💰'],
                            ['id' => 'ben_dev',    'label' => 'Professional Development','icon' => '📚'],
                            ['id' => 'ben_gym',    'label' => 'Gym Membership',          'icon' => '💪'],
                        ];
                        foreach ($benefits as $b): ?>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-violet-200 hover:bg-violet-50 cursor-pointer transition-all has-[:checked]:border-violet-300 has-[:checked]:bg-violet-50">
                            <input type="checkbox" name="benefits[]" value="<?= $b['id'] ?>"
                                class="w-4 h-4 rounded accent-violet-600 cursor-pointer">
                            <span class="text-sm text-gray-700 font-medium"><?= $b['icon'] ?> <?= $b['label'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- §5 Additional Terms -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">5</span>
                        Additional Terms
                    </h3>
                    <textarea id="offerTerms" rows="3"
                        placeholder="Add any additional conditions, requirements, or notes to be included in the offer letter..."
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent resize-none transition-shadow"></textarea>
                </div>

                <!-- §6 AI Letter -->
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 text-xs flex items-center justify-center font-bold flex-shrink-0">6</span>
                        AI Offer Letter
                    </h3>
                    <button type="button" onclick="generateAILetter()" id="aiGenerateBtn"
                        class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-colors shadow-sm">
                        <span aria-hidden="true">✨</span>
                        Generate Offer Letter with AI
                    </button>

                    <!-- Loading -->
                    <div id="aiLoadingState" class="hidden mt-4 flex items-center gap-3 p-4 bg-violet-50 rounded-xl border border-violet-100">
                        <div class="flex gap-1 flex-shrink-0">
                            <div class="w-2 h-2 bg-violet-500 rounded-full animate-bounce" style="animation-delay:0s"></div>
                            <div class="w-2 h-2 bg-violet-500 rounded-full animate-bounce" style="animation-delay:0.15s"></div>
                            <div class="w-2 h-2 bg-violet-500 rounded-full animate-bounce" style="animation-delay:0.3s"></div>
                        </div>
                        <span class="text-sm text-violet-700 font-medium">AI is crafting your offer letter&hellip;</span>
                    </div>

                    <!-- Preview -->
                    <div id="aiLetterPreview" class="hidden mt-4">
                        <div class="rounded-xl border border-violet-200 bg-white overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-2.5 bg-violet-50 border-b border-violet-100">
                                <span class="text-xs font-bold text-violet-700 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    AI Generated Preview
                                </span>
                                <div class="flex gap-2">
                                    <button type="button" onclick="generateAILetter()" class="text-xs text-violet-600 hover:text-violet-800 font-semibold px-2 py-1 rounded-lg hover:bg-violet-100 transition-colors">↺ Regenerate</button>
                                    <button type="button" id="aiAcceptBtn" onclick="acceptAILetter()" class="text-xs bg-violet-600 hover:bg-violet-700 text-white font-semibold px-3 py-1 rounded-lg transition-colors">Accept</button>
                                </div>
                            </div>
                            <div id="aiLetterContent" class="p-5 text-sm text-gray-700 leading-relaxed space-y-3 max-h-60 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>

            </div><!-- /scrollable body -->

            <!-- Footer -->
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50/60 rounded-b-2xl">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors">
                    Cancel
                </button>
                <div class="flex gap-2">
                    <button type="button" onclick="saveAsDraft()" class="px-4 py-2 text-sm font-medium border border-violet-300 text-violet-600 rounded-full hover:bg-violet-50 transition-colors">
                        Save as Draft
                    </button>
                    <button type="button" onclick="submitSendOffer()" class="px-4 py-2 text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white rounded-full transition-colors shadow-sm">
                        Send to Candidate
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     VIEW OFFER MODAL
══════════════════════════════════════════════════════════ -->
<div id="viewOfferModal" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeViewModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col pointer-events-auto">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h2 class="text-lg font-bold text-gray-900" id="viewModalTitle">Offer Letter</h2>
                    <p class="text-xs text-gray-400 mt-0.5" id="viewModalSubtitle">Review offer details</p>
                </div>
                <button onclick="closeViewModal()" aria-label="Close" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 px-6 py-5">

                <!-- Candidate header -->
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl mb-5">
                    <div class="w-12 h-12 rounded-full bg-violet-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0 select-none" id="viewAvatarInitials"></div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-900 text-base" id="viewCandidateName"></p>
                        <p class="text-sm text-gray-500" id="viewCandidateEmail"></p>
                        <p class="text-xs text-gray-400 mt-0.5" id="viewCandidatePosition"></p>
                    </div>
                    <div class="flex-shrink-0" id="viewStatusBadgeWrap"></div>
                </div>

                <!-- Letter -->
                <div class="border border-gray-100 rounded-xl p-6 bg-white text-sm text-gray-700 leading-relaxed" id="viewLetterBody"
                    style="font-family:Georgia,'Times New Roman',serif"></div>

                <!-- Meta grid -->
                <div class="mt-4 grid grid-cols-2 gap-3" id="viewMetaInfo"></div>

            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0 bg-gray-50/60 rounded-b-2xl">
                <button onclick="closeViewModal()" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition-colors">
                    Close
                </button>
                <div class="flex gap-2" id="viewModalActions"></div>
            </div>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════ -->
<script>
/* ─── Data ─────────────────────────────────────────────── */
const offersData = <?php echo json_encode(array_values($mockOffers), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

const mockCandidates = [
    {name:'Jordan Lee',     initials:'JL', email:'jordan@example.com',  position:'Frontend Developer'},
    {name:'Chloe Davis',    initials:'CD', email:'chloe@example.com',   position:'Product Designer'},
    {name:'Ryan Park',      initials:'RP', email:'ryan@example.com',    position:'Solutions Architect'},
    {name:'Amelia Brooks',  initials:'AB', email:'amelia@example.com',  position:'Data Analyst'},
    {name:'Nathan Clark',   initials:'NC', email:'nathan@example.com',  position:'Backend Engineer'},
    {name:'Sofia Garcia',   initials:'SG', email:'sofia@example.com',   position:'QA Engineer'},
    {name:'Tyler Hughes',   initials:'TH', email:'tyler@example.com',   position:'Cloud Engineer'},
    {name:'Maya Robinson',  initials:'MR', email:'maya@example.com',    position:'UX Researcher'},
];

const currencySymbols = {USD:'$', EUR:'€', GBP:'£', CAD:'C$', AUD:'A$'};
const statusBadgeMap  = {
    draft:    'bg-gray-100 text-gray-600',
    pending:  'bg-amber-100 text-amber-700',
    accepted: 'bg-green-100 text-green-700',
    declined: 'bg-red-100 text-red-700',
};

let currentSalaryType   = 'monthly';
let selectedCandidate   = null;
let currentViewOfferId  = null;
let aiLetterAccepted    = false;
let searchDebounceTimer = null;

/* ─── Tab Switching ─────────────────────────────────────── */
function switchTab(key) {
    const url = new URL(window.location.href);
    url.searchParams.set('status', key);
    window.history.pushState({}, '', url.toString());

    document.querySelectorAll('[data-tab]').forEach(btn => {
        const active = btn.dataset.tab === key;
        btn.className = active
            ? 'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-violet-600 text-white transition-all whitespace-nowrap'
            : 'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all whitespace-nowrap';
        const badge = btn.querySelector('span');
        if (badge) {
            badge.className = active
                ? 'bg-violet-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full'
                : 'bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full';
        }
    });

    let visible = 0;
    document.querySelectorAll('.offer-card').forEach(card => {
        const show = (key === 'all' || card.dataset.status === key);
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const empty = document.getElementById('emptyState');
    if (empty) empty.classList.toggle('hidden', visible > 0);
}

/* ─── Create Modal ──────────────────────────────────────── */
function openCreateModal() {
    document.getElementById('createOfferModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCreateModal() {
    document.getElementById('createOfferModal').classList.add('hidden');
    document.body.style.overflow = '';
    _resetCreateForm();
}

function _resetCreateForm() {
    clearCandidate();
    const si = document.getElementById('candidateSearch');
    if (si) si.value = '';
    hideCandidateDropdown();

    ['offerPosition', 'offerStartDate', 'offerExpiryDate', 'offerSalary', 'offerTerms'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    document.querySelectorAll('input[name="benefits[]"]').forEach(cb => (cb.checked = false));
    setSalaryType('monthly');

    document.getElementById('aiLoadingState').classList.add('hidden');
    document.getElementById('aiLetterPreview').classList.add('hidden');

    const btn = document.getElementById('aiGenerateBtn');
    if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }

    const acceptBtn = document.getElementById('aiAcceptBtn');
    if (acceptBtn) acceptBtn.textContent = 'Accept';

    aiLetterAccepted = false;

    // Reset dates to sensible defaults
    const today   = new Date();
    const start   = new Date(today); start.setDate(start.getDate() + 14);
    const expiry  = new Date(today); expiry.setDate(expiry.getDate() + 7);
    const fmt     = d => d.toISOString().split('T')[0];

    const sdEl = document.getElementById('offerStartDate');
    const exEl = document.getElementById('offerExpiryDate');
    if (sdEl) { sdEl.min = fmt(today); sdEl.value = fmt(start); }
    if (exEl) { exEl.min = fmt(today); exEl.value = fmt(expiry); }
}

/* ─── Candidate Search ──────────────────────────────────── */
function handleCandidateSearch(val) {
    clearTimeout(searchDebounceTimer);
    if (val.trim().length < 2) { hideCandidateDropdown(); return; }
    searchDebounceTimer = setTimeout(() => {
        const q = val.toLowerCase();
        const results = mockCandidates
            .filter(c => c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q) || c.position.toLowerCase().includes(q))
            .slice(0, 5);
        _renderCandidateDropdown(results);
    }, 220);
}

function _renderCandidateDropdown(results) {
    const dd = document.getElementById('candidateDropdown');
    if (!dd) return;
    dd.innerHTML = results.length === 0
        ? '<div class="px-4 py-3 text-sm text-gray-400">No matching candidates</div>'
        : results.map(c => `
            <button type="button" onclick='selectCandidate(${JSON.stringify(c)})'
                class="w-full flex items-center gap-3 px-4 py-3 hover:bg-violet-50 transition-colors text-left border-b border-gray-50 last:border-0">
                <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 select-none">${_esc(c.initials)}</div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800">${_esc(c.name)}</p>
                    <p class="text-xs text-gray-400 truncate">${_esc(c.position)} &bull; ${_esc(c.email)}</p>
                </div>
            </button>`).join('');
    dd.classList.remove('hidden');
}

function hideCandidateDropdown() {
    const dd = document.getElementById('candidateDropdown');
    if (dd) dd.classList.add('hidden');
}

function selectCandidate(candidate) {
    selectedCandidate = candidate;
    hideCandidateDropdown();

    document.getElementById('searchWrapper').classList.add('hidden');
    document.getElementById('selectedCandidatePill').classList.remove('hidden');
    document.getElementById('selectedInitials').textContent = candidate.initials;
    document.getElementById('selectedName').textContent = candidate.name;

    const pos = document.getElementById('offerPosition');
    if (pos && !pos.value) pos.value = candidate.position;
}

function clearCandidate() {
    selectedCandidate = null;
    document.getElementById('searchWrapper').classList.remove('hidden');
    document.getElementById('selectedCandidatePill').classList.add('hidden');
    const si = document.getElementById('candidateSearch');
    if (si) { si.value = ''; si.focus(); }
}

// Close dropdown on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('#searchWrapper') && !e.target.closest('#candidateDropdown')) {
        hideCandidateDropdown();
    }
});

/* ─── Salary Type Toggle ────────────────────────────────── */
function setSalaryType(type) {
    currentSalaryType = type;
    const monthly = document.getElementById('typeMonthly');
    const annual  = document.getElementById('typeAnnual');
    if (!monthly || !annual) return;

    const activeCls  = 'px-5 py-2 text-sm font-semibold rounded-lg transition-all bg-white shadow-sm text-violet-700 border border-violet-200';
    const inactiveCls = 'px-5 py-2 text-sm font-medium rounded-lg transition-all text-gray-500 hover:text-gray-700';

    monthly.className = type === 'monthly' ? activeCls : inactiveCls;
    annual.className  = type === 'annual'  ? activeCls : inactiveCls;
}

/* ─── Currency ──────────────────────────────────────────── */
function updateCurrencySymbol() {
    const sel = document.getElementById('offerCurrency');
    const sym = document.getElementById('currencySymbol');
    if (sel && sym) sym.textContent = currencySymbols[sel.value] || '$';
}

function formatSalaryInput(input) {
    const raw = input.value.replace(/[^\d]/g, '');
    input.value = raw ? parseInt(raw, 10).toLocaleString('en-US') : '';
}

/* ─── AI Letter Generation ──────────────────────────────── */
function generateAILetter() {
    const loading    = document.getElementById('aiLoadingState');
    const preview    = document.getElementById('aiLetterPreview');
    const btn        = document.getElementById('aiGenerateBtn');
    const acceptBtn  = document.getElementById('aiAcceptBtn');

    preview.classList.add('hidden');
    loading.classList.remove('hidden');
    btn.disabled = true;
    btn.classList.add('opacity-60');
    if (acceptBtn) acceptBtn.textContent = 'Accept';
    aiLetterAccepted = false;

    const candidateName = selectedCandidate ? selectedCandidate.name : 'the Candidate';
    const position      = document.getElementById('offerPosition')?.value  || 'the position';
    const salary        = document.getElementById('offerSalary')?.value    || 'competitive';
    const currency      = document.getElementById('offerCurrency')?.value  || 'USD';
    const sym           = currencySymbols[currency] || '$';
    const salaryType    = currentSalaryType === 'monthly' ? 'per month' : 'per annum';
    const startDate     = document.getElementById('offerStartDate')?.value  || '';
    const expiryDate    = document.getElementById('offerExpiryDate')?.value || '';

    fetch('/api/v1/offers?action=generate', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({candidate: candidateName, position, salary, currency, salaryType})
    }).catch(() => {});

    setTimeout(() => {
        loading.classList.add('hidden');
        btn.disabled = false;
        btn.classList.remove('opacity-60');

        const fmtDate = iso => iso
            ? new Date(iso + 'T00:00:00').toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'})
            : null;

        const today   = new Date().toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
        const start   = fmtDate(startDate)  || 'a mutually agreed date';
        const expiry  = fmtDate(expiryDate) || '7 days from the date of this letter';

        document.getElementById('aiLetterContent').innerHTML = `
            <p class="text-gray-400 text-xs">${today}</p>
            <p class="font-semibold text-gray-800">Dear ${_esc(candidateName)},</p>
            <p>We are delighted to extend this formal offer of employment for the position of <strong>${_esc(position)}</strong> at <strong>HireAI Technologies</strong>. Following our thorough review process, we are confident that your skills, experience, and values align perfectly with our team's vision and culture.</p>
            <p><strong>Compensation Package:</strong> We are pleased to offer a base salary of <strong class="text-amber-600">${sym}${_esc(salary)} ${salaryType}</strong>, payable in accordance with our standard payroll schedule.</p>
            <p><strong>Start Date:</strong> We propose a start date of <strong>${start}</strong>, though we are open to discussing this to accommodate your transition.</p>
            <p><strong>Benefits:</strong> In addition to your base compensation, you will be entitled to our comprehensive benefits package, details of which are enclosed with this letter.</p>
            <p>This offer is contingent upon the successful completion of a standard background check. Please review the enclosed terms carefully and return a signed copy by <strong>${expiry}</strong>.</p>
            <p>We look forward to welcoming you to the team.</p>
            <p>Warm regards,<br><strong>The Hiring Team</strong><br>HireAI Technologies</p>`;

        preview.classList.remove('hidden');
    }, 1500);
}

function acceptAILetter() {
    aiLetterAccepted = true;
    const btn = document.getElementById('aiAcceptBtn');
    if (btn) {
        btn.textContent = '✓ Accepted';
        btn.className = 'text-xs bg-green-500 text-white font-semibold px-3 py-1 rounded-lg cursor-default';
        btn.onclick = null;
    }
    showToast('AI letter accepted — ready to send.', 'success');
}

/* ─── Form Data Helpers ─────────────────────────────────── */
function _gatherFormData() {
    const benefits = [];
    document.querySelectorAll('input[name="benefits[]"]:checked').forEach(cb => benefits.push(cb.value));
    return {
        candidate:       selectedCandidate,
        position:        document.getElementById('offerPosition')?.value   || '',
        startDate:       document.getElementById('offerStartDate')?.value  || '',
        expiryDate:      document.getElementById('offerExpiryDate')?.value || '',
        salary:          (document.getElementById('offerSalary')?.value || '').replace(/,/g, ''),
        currency:        document.getElementById('offerCurrency')?.value   || 'USD',
        salaryType:      currentSalaryType,
        benefits,
        additionalTerms: document.getElementById('offerTerms')?.value      || '',
        aiLetterAccepted,
    };
}

/* ─── Save / Send from Modal ────────────────────────────── */
function saveAsDraft() {
    const payload = _gatherFormData();
    payload.status = 'draft';
    fetch('/api/v1/offers', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(payload)
    }).catch(() => {});
    showToast('Offer saved as draft.', 'success');
    closeCreateModal();
}

function submitSendOffer() {
    if (!confirm('Send this offer to the candidate? They will receive an email notification.')) return;
    const payload = _gatherFormData();
    payload.status = 'pending';
    fetch('/api/v1/offers', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(payload)
    }).catch(() => {});
    showToast('Offer sent to candidate!', 'success');
    closeCreateModal();
}

/* ─── Card Actions ──────────────────────────────────────── */
function confirmSendCard(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    if (!confirm(`Send the offer to ${offer.name}?`)) return;
    fetch('/api/v1/offers', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({idx, status: 'pending'})
    }).catch(() => {});
    showToast(`Offer sent to ${offer.name}!`, 'success');
}

function resendOffer(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    fetch(`/api/v1/offers`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({idx, action: 'resend'})
    }).catch(() => {});
    showToast(`Offer resent to ${offer.name}.`, 'success');
}

function withdrawOffer(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    if (!confirm(`Withdraw the offer for ${offer.name}? This cannot be undone.`)) return;
    fetch(`/api/v1/offers/${idx}/withdraw`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}
    }).catch(() => {});
    showToast('Offer withdrawn.', 'info');
    closeViewModal();
}

function markAsHired(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    if (!confirm(`Mark ${offer.name} as hired? This will update their candidate profile.`)) return;
    fetch(`/api/v1/offers/${idx}/hire`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}
    }).catch(() => {});
    showToast(`${offer.name} marked as hired!`, 'success');
}

function deleteOffer(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    if (!confirm(`Delete this draft offer for ${offer.name}? This cannot be undone.`)) return;
    fetch(`/api/v1/offers/${idx}`, {
        method: 'DELETE',
        headers: {'X-Requested-With':'XMLHttpRequest'}
    }).catch(() => {});
    const card = document.querySelector(`.offer-card[data-id="offer-${idx}"]`);
    if (card) {
        card.style.transition = 'opacity 0.3s, transform 0.3s';
        card.style.opacity = '0';
        card.style.transform = 'translateX(16px)';
        setTimeout(() => card.remove(), 320);
    }
    showToast('Draft offer deleted.', 'success');
}

function downloadPDF(idx) {
    showToast('Preparing PDF download…', 'info');
    window.open(`/api/v1/offers/${idx}/pdf`, '_blank');
}

/* ─── View Modal ────────────────────────────────────────── */
function openViewModal(idx) {
    const offer = offersData[idx];
    if (!offer) return;
    currentViewOfferId = idx;

    document.getElementById('viewModalTitle').textContent   = `${offer.position} — Offer Letter`;
    document.getElementById('viewModalSubtitle').textContent = `Issued to ${offer.name}`;
    document.getElementById('viewAvatarInitials').textContent = offer.initials;
    document.getElementById('viewCandidateName').textContent  = offer.name;
    document.getElementById('viewCandidateEmail').textContent = offer.email;
    document.getElementById('viewCandidatePosition').textContent = `${offer.dept} Department`;

    const badgeCls = statusBadgeMap[offer.status] || 'bg-gray-100 text-gray-600';
    document.getElementById('viewStatusBadgeWrap').innerHTML =
        `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${badgeCls}">
            ${_cap(offer.status)}
        </span>`;

    const salaryLabel = `$${offer.salary} / ${offer.type}`;
    const today = new Date().toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});

    document.getElementById('viewLetterBody').innerHTML = `
        <div class="text-center pb-5 mb-5 border-b border-gray-100">
            <p class="font-bold text-gray-900 text-lg tracking-wide">HireAI Technologies</p>
            <p class="text-xs text-gray-400 mt-0.5 uppercase tracking-widest">Official Offer of Employment</p>
        </div>
        <p class="text-gray-400 text-xs mb-3">${today}</p>
        <p><strong>Dear ${_esc(offer.name)},</strong></p>
        <p>We are pleased to formally offer you the position of <strong>${_esc(offer.position)}</strong> within our <strong>${_esc(offer.dept)}</strong> department at HireAI Technologies. This letter serves as your official offer of employment and outlines the key terms and conditions of your engagement with our company.</p>
        <p><strong>Compensation:</strong> Your base salary will be <strong class="text-amber-600">${salaryLabel}</strong>${offer.benefits ? ', supplemented by our comprehensive employee benefits package including health, dental, and vision coverage' : ''}.</p>
        <p><strong>Employment Type:</strong> This is a full-time, permanent position subject to a 90-day probationary period during which your performance and fit will be assessed.</p>
        <p><strong>Reporting Structure:</strong> You will report to the Head of ${_esc(offer.dept)} and work closely with cross-functional teams across the organization.</p>
        <p>We believe your unique skills and experience make you an excellent addition to our team. We look forward to seeing the positive impact you will have on our mission to transform recruitment through AI.</p>
        <p>Please sign and return a copy of this letter to confirm your acceptance.</p>
        <p class="mt-4">Sincerely,<br><strong>The Hiring Team</strong><br>HireAI Technologies</p>
        <div class="mt-8 pt-5 border-t border-gray-100">
            <p class="text-xs text-gray-400 font-semibold mb-3 uppercase tracking-wider">Candidate Acceptance</p>
            <div class="flex gap-10">
                <div>
                    <div class="w-44 border-b border-gray-300 mb-1 h-7"></div>
                    <p class="text-xs text-gray-400">Signature</p>
                </div>
                <div>
                    <div class="w-32 border-b border-gray-300 mb-1 h-7"></div>
                    <p class="text-xs text-gray-400">Date</p>
                </div>
            </div>
        </div>`;

    const expText = (offer.status === 'pending' && offer.expires_days > 0)
        ? `<span class="${offer.expires_days <= 3 ? 'text-red-500 font-semibold' : 'text-amber-600 font-semibold'}">Expires in ${offer.expires_days} day${offer.expires_days !== 1 ? 's' : ''}</span>`
        : '<span class="text-gray-500">—</span>';

    document.getElementById('viewMetaInfo').innerHTML = `
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-400 mb-0.5">Status</p>
            <p class="text-sm font-semibold text-gray-700 capitalize">${offer.status}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-400 mb-0.5">Salary</p>
            <p class="text-sm font-semibold text-amber-600">${salaryLabel}</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-400 mb-0.5">Expiry</p>
            <div class="text-sm">${expText}</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-400 mb-0.5">Benefits</p>
            <p class="text-sm font-semibold text-gray-700">${offer.benefits ? 'Included' : 'Not included'}</p>
        </div>`;

    let actions = `
        <button onclick="downloadPDF(${idx})" class="px-4 py-2 text-sm font-medium border border-gray-200 text-gray-600 rounded-full hover:bg-gray-50 transition-colors">
            Download PDF
        </button>`;

    if (offer.status === 'pending') {
        actions += `
        <button onclick="withdrawOffer(${idx})" class="px-4 py-2 text-sm font-medium border border-amber-200 text-amber-600 rounded-full hover:bg-amber-50 transition-colors">
            Withdraw Offer
        </button>`;
    }
    if (offer.status === 'accepted') {
        actions += `
        <button onclick="markAsHired(${idx})" class="px-4 py-2 text-sm font-medium bg-green-500 hover:bg-green-600 text-white rounded-full transition-colors">
            Mark as Hired
        </button>`;
    }
    if (offer.status === 'declined') {
        actions += `
        <button onclick="closeViewModal(); openCreateModal();" class="px-4 py-2 text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white rounded-full transition-colors">
            Create New Offer
        </button>`;
    }

    document.getElementById('viewModalActions').innerHTML = actions;
    document.getElementById('viewOfferModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewOfferModal').classList.add('hidden');
    document.body.style.overflow = '';
    currentViewOfferId = null;
}

/* ─── Keyboard Shortcuts ────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeCreateModal();
        closeViewModal();
    }
});

/* ─── Toast Notifications ───────────────────────────────── */
function showToast(message, type) {
    type = type || 'success';
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const colors = {success:'bg-green-500', error:'bg-red-500', info:'bg-blue-500', warning:'bg-amber-500'};
    const icons  = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        info:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
    };

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-white text-sm font-medium max-w-xs ${colors[type] || colors.success}`;
    toast.style.cssText = 'transform:translateX(120%);transition:transform 0.28s cubic-bezier(.22,.61,.36,1),opacity 0.28s ease;';
    toast.innerHTML = `
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type] || icons.success}</svg>
        <span>${_esc(message)}</span>
        <button onclick="this.parentElement.remove()" class="ml-auto w-4 h-4 flex items-center justify-center flex-shrink-0 opacity-70 hover:opacity-100">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>`;

    container.appendChild(toast);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
    });

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(120%)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/* ─── Utilities ─────────────────────────────────────────── */
function _esc(str) {
    return String(str ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function _cap(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

/* ─── Init ──────────────────────────────────────────────── */
(function init() {
    // Pre-set default dates on the create modal
    const today  = new Date();
    const start  = new Date(today); start.setDate(start.getDate() + 14);
    const expiry = new Date(today); expiry.setDate(expiry.getDate() + 7);
    const fmt    = d => d.toISOString().split('T')[0];

    const sdEl = document.getElementById('offerStartDate');
    const exEl = document.getElementById('offerExpiryDate');
    if (sdEl) { sdEl.min = fmt(today); sdEl.value = fmt(start); }
    if (exEl) { exEl.min = fmt(today); exEl.value = fmt(expiry); }

    // Evaluate initial empty state
    const activeKey = new URLSearchParams(window.location.search).get('status') || 'all';
    const visible   = [...document.querySelectorAll('.offer-card')]
        .filter(c => activeKey === 'all' || c.dataset.status === activeKey).length;
    const empty = document.getElementById('emptyState');
    if (empty) empty.classList.toggle('hidden', visible > 0);
})();
</script>
