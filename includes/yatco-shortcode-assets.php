<?php
/**
 * Shortcode Assets
 * 
 * Contains CSS and JavaScript for the vessels shortcode display.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<style>
    .yatco-vessels-container {
        margin: 20px 0;
    }
    .yatco-filters {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .yatco-filters-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    .yatco-filters-row-2 {
        margin-bottom: 0;
    }
    .yatco-filter-group {
        flex: 1;
        min-width: 150px;
    }
    .yatco-filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 14px;
        color: #333;
    }
    .yatco-filter-input,
    .yatco-filter-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .yatco-input-small {
        width: 80px;
    }
    .yatco-filter-range {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .yatco-filter-toggle {
        display: flex;
        margin-top: 8px;
        gap: 0;
    }
    .yatco-toggle-btn {
        padding: 6px 16px;
        border: 1px solid #0073aa;
        background: #fff;
        color: #0073aa;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .yatco-toggle-btn:first-child {
        border-top-left-radius: 4px;
        border-bottom-left-radius: 4px;
    }
    .yatco-toggle-btn:last-child {
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
        border-left: none;
    }
    .yatco-toggle-btn.active {
        background: #0073aa;
        color: #fff;
    }
    .yatco-filter-actions {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }
    .yatco-search-btn,
    .yatco-reset-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .yatco-search-btn {
        background: #0073aa;
        color: #fff;
    }
    .yatco-search-btn:hover {
        background: #005a87;
    }
    .yatco-reset-btn {
        background: #ddd;
        color: #333;
    }
    .yatco-reset-btn:hover {
        background: #ccc;
    }
    .yatco-results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 10px 0;
    }
    .yatco-results-count {
        font-weight: 600;
        color: #333;
    }
    .yatco-sort-view {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .yatco-sort-select {
        padding: 6px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .yatco-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin: 30px 0;
        padding: 20px 0;
        flex-wrap: wrap;
    }
    .yatco-pagination-btn {
        padding: 10px 20px;
        border: 1px solid #0073aa;
        background: #fff;
        color: #0073aa;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }
    .yatco-pagination-btn:hover {
        background: #0073aa;
        color: #fff;
    }
    .yatco-pagination-btn.active {
        background: #0073aa;
        color: #fff;
        font-weight: 700;
    }
    .yatco-page-info {
        font-weight: 600;
        color: #333;
        margin-left: 15px;
    }
    .yatco-page-dots {
        padding: 10px 5px;
        color: #666;
    }
    .yatco-pagination-btn.yatco-page-num {
        min-width: 40px;
    }
    .yatco-loading-note {
        font-size: 12px;
        color: #666;
        font-style: italic;
        margin-top: 5px;
    }
    .yatco-vessels-grid {
        display: grid;
        gap: 20px;
        margin: 20px 0;
    }
    .yatco-vessels-grid.yatco-col-1 { grid-template-columns: 1fr; }
    .yatco-vessels-grid.yatco-col-2 { grid-template-columns: repeat(2, 1fr); }
    .yatco-vessels-grid.yatco-col-3 { grid-template-columns: repeat(3, 1fr); }
    .yatco-vessels-grid.yatco-col-4 { grid-template-columns: repeat(4, 1fr); }
    .yatco-vessel-card {
        display: block;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow 0.3s;
        text-decoration: none;
        color: inherit;
    }
    .yatco-vessel-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-decoration: none;
    }
    .yatco-vessel-card:visited {
        color: inherit;
    }
    .yatco-vessel-image {
        width: 100%;
        padding-top: 75%;
        position: relative;
        overflow: hidden;
        background: #f5f5f5;
    }
    .yatco-vessel-image img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .yatco-vessel-info {
        padding: 15px;
    }
    .yatco-vessel-name {
        margin: 0 0 10px 0;
        font-size: 18px;
        font-weight: 600;
    }
    .yatco-vessel-details {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 14px;
        color: #666;
    }
    .yatco-vessel-price {
        font-weight: 600;
        color: #0073aa;
    }
    @media (max-width: 768px) {
        .yatco-vessels-grid.yatco-col-2,
        .yatco-vessels-grid.yatco-col-3,
        .yatco-vessels-grid.yatco-col-4 {
            grid-template-columns: 1fr;
        }
        .yatco-filters-row {
            flex-direction: column;
        }
        .yatco-filter-group {
            min-width: 100%;
        }
        .yatco-results-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>
<script>
(function() {
    const container = document.querySelector('.yatco-vessels-container');
    if (!container) return;
    
    const currency = container.dataset.currency || 'USD';
    const lengthUnit = container.dataset.lengthUnit || 'FT';
    const allVessels = Array.from(document.querySelectorAll('.yatco-vessel-card'));
    const grid = document.getElementById('yatco-vessels-grid');
    const resultsCount = document.querySelector('.yatco-results-count');
    const totalCount = document.getElementById('yatco-total-count');
    
    // Filter elements
    const keywords = document.getElementById('yatco-keywords');
    const builder = document.getElementById('yatco-builder');
    const yearMin = document.getElementById('yatco-year-min');
    const yearMax = document.getElementById('yatco-year-max');
    const loaMin = document.getElementById('yatco-loa-min');
    const loaMax = document.getElementById('yatco-loa-max');
    const priceMin = document.getElementById('yatco-price-min');
    const priceMax = document.getElementById('yatco-price-max');
    const condition = document.getElementById('yatco-condition');
    const type = document.getElementById('yatco-type');
    const category = document.getElementById('yatco-category');
    const cabins = document.getElementById('yatco-cabins');
    const sort = document.getElementById('yatco-sort');
    const searchBtn = document.getElementById('yatco-search-btn');
    const resetBtn = document.getElementById('yatco-reset-btn');
    
    // Toggle buttons
    const lengthBtns = document.querySelectorAll('.yatco-toggle-btn[data-unit]');
    const currencyBtns = document.querySelectorAll('.yatco-toggle-btn[data-currency]');
    
    let currentCurrency = currency;
    let currentLengthUnit = lengthUnit;
    
    function updateToggleButtons() {
        lengthBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.unit === currentLengthUnit);
        });
        currencyBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.currency === currentCurrency);
        });
    }
    
    lengthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentLengthUnit = this.dataset.unit;
            updateToggleButtons();
            filterAndDisplay();
        });
    });
    
    currencyBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentCurrency = this.dataset.currency;
            updateToggleButtons();
            filterAndDisplay();
        });
    });
    
    function filterVessels() {
        const keywordVal = keywords ? keywords.value.toLowerCase() : '';
        const builderVal = builder ? builder.value : '';
        const yearMinVal = yearMin ? parseInt(yearMin.value) : null;
        const yearMaxVal = yearMax ? parseInt(yearMax.value) : null;
        const loaMinVal = loaMin ? parseFloat(loaMin.value) : null;
        const loaMaxVal = loaMax ? parseFloat(loaMax.value) : null;
        const priceMinVal = priceMin ? parseFloat(priceMin.value) : null;
        const priceMaxVal = priceMax ? parseFloat(priceMax.value) : null;
        const conditionVal = condition ? condition.value : '';
        const typeVal = type ? type.value : '';
        const categoryVal = category ? category.value : '';
        const cabinsVal = cabins ? parseInt(cabins.value) : null;
        
        return allVessels.filter(vessel => {
            const name = vessel.dataset.name || '';
            const location = vessel.dataset.location || '';
            const vesselBuilder = vessel.dataset.builder || '';
            const vesselCategory = vessel.dataset.category || '';
            const vesselType = vessel.dataset.type || '';
            const vesselCondition = vessel.dataset.condition || '';
            const year = parseInt(vessel.dataset.year) || 0;
            const loaFeet = parseFloat(vessel.dataset.loaFeet) || 0;
            const loaMeters = parseFloat(vessel.dataset.loaMeters) || 0;
            const priceUsd = parseFloat(vessel.dataset.priceUsd) || 0;
            const priceEur = parseFloat(vessel.dataset.priceEur) || 0;
            const stateRooms = parseInt(vessel.dataset.stateRooms) || 0;
            
            // Keywords
            if (keywordVal && !name.includes(keywordVal) && !location.includes(keywordVal)) {
                return false;
            }
            
            // Builder
            if (builderVal && vesselBuilder !== builderVal) {
                return false;
            }
            
            // Year
            if (yearMinVal && (year === 0 || year < yearMinVal)) {
                return false;
            }
            if (yearMaxVal && (year === 0 || year > yearMaxVal)) {
                return false;
            }
            
            // Length
            const loa = currentLengthUnit === 'M' ? loaMeters : loaFeet;
            if (loaMinVal && (loa === 0 || loa < loaMinVal)) {
                return false;
            }
            if (loaMaxVal && (loa === 0 || loa > loaMaxVal)) {
                return false;
            }

            // Price
            const price = currentCurrency === 'EUR' ? priceEur : priceUsd;
            if (priceMinVal && (price === 0 || price < priceMinVal)) {
                return false;
            }
            if (priceMaxVal && (price === 0 || price > priceMaxVal)) {
                return false;
            }
            
            // Condition
            if (conditionVal && vesselCondition !== conditionVal) {
                return false;
            }
            
            // Type
            if (typeVal && vesselType !== typeVal) {
                return false;
            }
            
            // Category
            if (categoryVal && vesselCategory !== categoryVal) {
                return false;
            }
            
            // Cabins
            if (cabinsVal && stateRooms < cabinsVal) {
                return false;
            }
            
            return true;
        });
    }
    
    function sortVessels(vessels) {
        const sortVal = sort ? sort.value : '';
        if (!sortVal) return vessels;
        
        return [...vessels].sort((a, b) => {
            switch(sortVal) {
                case 'price_asc':
                    const priceA = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                    const priceB = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                    return priceA - priceB;
                case 'price_desc':
                    const priceA2 = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                    const priceB2 = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                    return priceB2 - priceA2;
                case 'year_desc':
                    return (parseInt(b.dataset.year) || 0) - (parseInt(a.dataset.year) || 0);
                case 'year_asc':
                    return (parseInt(a.dataset.year) || 0) - (parseInt(b.dataset.year) || 0);
                case 'length_desc':
                    const loaA = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                    const loaB = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                    return loaB - loaA;
                case 'length_asc':
                    const loaA2 = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                    const loaB2 = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                    return loaA2 - loaB2;
                case 'name_asc':
                    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                default:
                    return 0;
            }
        });
    }
    
    // Pagination - shows 24 vessels per page
    let currentPage = 1;
    const vesselsPerPage = 24;
    
    // Get pagination range with ellipsis for large page counts (e.g., 1 ... 5 6 7 ... 140)
    function getPaginationRange(current, total) {
        const delta = 2;
        const range = [];
        const rangeWithDots = [];
        
        if (total <= 7) {
            // Show all pages if 7 or fewer
            for (let i = 1; i <= total; i++) {
                rangeWithDots.push(i);
            }
            return rangeWithDots;
        }
        
        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
            range.push(i);
        }
        
        if (current - delta > 2) {
            rangeWithDots.push(1, '...');
        } else {
            rangeWithDots.push(1);
        }
        
        rangeWithDots.push(...range);
        
        if (current + delta < total - 1) {
            rangeWithDots.push('...', total);
        } else {
            if (total > 1) {
                rangeWithDots.push(total);
            }
        }
        
        return rangeWithDots;
    }
    
    function paginateVessels(vessels) {
        const start = (currentPage - 1) * vesselsPerPage;
        const end = start + vesselsPerPage;
        return vessels.slice(start, end);
    }
    
    function updatePaginationControls(totalVessels) {
        const totalPages = Math.ceil(totalVessels / vesselsPerPage);
        if (totalPages <= 1) {
            // Hide pagination if only one page
            const paginationContainer = document.querySelector('.yatco-pagination');
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            }
            return;
        }
        
        const pageRange = getPaginationRange(currentPage, totalPages);
        let paginationHtml = '<div class="yatco-pagination">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<button class="yatco-pagination-btn yatco-prev-btn" onclick="window.yatcoGoToPage(${currentPage - 1})">‹ Previous</button>`;
        }
        
        // Page numbers
        pageRange.forEach((page, index) => {
            if (page === '...') {
                paginationHtml += `<span class="yatco-page-dots">...</span>`;
            } else {
                const isActive = page === currentPage;
                paginationHtml += `<button class="yatco-pagination-btn yatco-page-num ${isActive ? 'active' : ''}" onclick="window.yatcoGoToPage(${page})">${page}</button>`;
            }
        });
        
        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<button class="yatco-pagination-btn yatco-next-btn" onclick="window.yatcoGoToPage(${currentPage + 1})">Next ›</button>`;
        }
        
        paginationHtml += `<span class="yatco-page-info">Page ${currentPage} of ${totalPages}</span>`;
        paginationHtml += '</div>';
        
        let paginationContainer = document.querySelector('.yatco-pagination');
        if (!paginationContainer) {
            paginationContainer = document.createElement('div');
            paginationContainer.className = 'yatco-pagination';
            if (grid && grid.parentNode) {
                grid.parentNode.insertBefore(paginationContainer, grid.nextSibling);
            }
        }
        paginationContainer.innerHTML = paginationHtml;
        paginationContainer.style.display = 'flex';
    }
    
    window.yatcoGoToPage = function(page) {
        currentPage = page;
        filterAndDisplay();
    };
    
    function filterAndDisplay() {
        const filtered = filterVessels();
        const sorted = sortVessels(filtered);
        const paginated = paginateVessels(sorted);
        
        // Hide all vessels
        allVessels.forEach(v => v.style.display = 'none');
        
        // Show paginated vessels
        paginated.forEach(v => {
            v.style.display = '';
            grid.appendChild(v);
        });
        
        // Update count
        if (resultsCount) {
            const totalFiltered = sorted.length;
            const shownStart = totalFiltered > 0 ? (currentPage - 1) * vesselsPerPage + 1 : 0;
            const shownEnd = Math.min(currentPage * vesselsPerPage, totalFiltered);
            const total = totalCount ? totalCount.textContent : allVessels.length;
            resultsCount.innerHTML = `${shownStart} - ${shownEnd} of <span id="yatco-total-count">${totalFiltered}</span> YACHTS FOUND`;
        }
        
        // Update pagination
        updatePaginationControls(sorted.length);
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (keywords) keywords.value = '';
            if (builder) builder.value = '';
            if (yearMin) yearMin.value = '';
            if (yearMax) yearMax.value = '';
            if (loaMin) loaMin.value = '';
            if (loaMax) loaMax.value = '';
            if (priceMin) priceMin.value = '';
            if (priceMax) priceMax.value = '';
            if (condition) condition.value = '';
            if (type) type.value = '';
            if (category) category.value = '';
            if (cabins) cabins.value = '';
            if (sort) sort.value = '';
            currentCurrency = currency;
            currentLengthUnit = lengthUnit;
            currentPage = 1;
            updateToggleButtons();
            filterAndDisplay();
        });
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            currentPage = 1;
            filterAndDisplay();
        });
    }
    
    if (sort) {
        sort.addEventListener('change', function() {
            currentPage = 1;
            filterAndDisplay();
        });
    }
    
    // Initialize
    updateToggleButtons();
    filterAndDisplay();
})();
</script>
<?php

