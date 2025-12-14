# API-Only Mode: Change Detection & Update Frequencies

## How Change Detection Works

The API-only approach detects changes through **two levels of caching**:

### 1. Vessel ID List (Detects Removed/Sold/New Vessels)
- **What it checks**: List of all active vessel IDs
- **How it detects changes**:
  - **Removed/Sold vessels**: When a vessel ID disappears from the `activevesselmlsid` endpoint
  - **New vessels**: When a new vessel ID appears in the list
- **Default cache duration**: **6 hours**
- **Configurable**: Yes (via admin settings)

### 2. Individual Vessel Data (Detects Price/Status Updates)
- **What it checks**: Full vessel specifications (price, status, days on market, etc.)
- **How it detects changes**:
  - **Price changes**: API always returns latest price
  - **Days on market**: Auto-increments daily in YATCO API
  - **Status changes**: `StatusText` field (e.g., "Sold", "Under Contract")
  - **Sold status**: Detected via `StatusText` or removal from active list
  - **All other fields**: Refreshed when cache expires
- **Default cache duration**: **1 hour**
- **Configurable**: Yes (via admin settings)

## Update Frequency Options

### Current Default Settings:
- **Vessel ID List**: 6 hours (detects removed/sold/new vessels)
- **Individual Vessel Data**: 1 hour (detects price/status changes)

### Recommended Settings by Use Case:

#### Option 1: Real-Time Updates (Most Frequent)
- **Vessel ID List**: 1 hour
- **Individual Vessel Data**: 15-30 minutes
- **Best for**: High-traffic sites, real estate agencies
- **Trade-off**: More API calls, but always up-to-date

#### Option 2: Balanced (Recommended)
- **Vessel ID List**: 6 hours
- **Individual Vessel Data**: 1 hour
- **Best for**: Most websites
- **Trade-off**: Good balance of freshness and performance

#### Option 3: Performance Optimized
- **Vessel ID List**: 12 hours
- **Individual Vessel Data**: 3 hours
- **Best for**: Low-traffic sites, cost-conscious
- **Trade-off**: Fewer API calls, but slightly less fresh data

#### Option 4: Daily Updates
- **Vessel ID List**: 24 hours
- **Individual Vessel Data**: 6 hours
- **Best for**: Low-priority listings
- **Trade-off**: Minimal API usage, but data may be up to 24 hours old

## What Gets Detected Automatically

### ✅ Automatically Detected:
1. **Price Changes** - When price updates in YATCO API
2. **Days on Market** - Auto-increments daily (from API)
3. **Status Changes** - Sold, Under Contract, etc. (via StatusText)
4. **Removed Vessels** - When vessel disappears from active list
5. **New Vessels** - When new vessel appears in active list
6. **All Field Updates** - Description, specs, images, etc.

### ⚠️ How Detection Works:

**Removed/Sold Vessels:**
- YATCO's `activevesselmlsid` endpoint only returns ACTIVE vessels
- If a vessel is sold/removed, it disappears from this list
- Plugin detects this when vessel ID list cache refreshes

**Price/Status Updates:**
- YATCO API always returns latest data
- When individual vessel cache expires, fresh data is fetched
- Changes are automatically reflected

**Days on Market:**
- This field auto-increments daily in YATCO's system
- No special detection needed - API always has current value

## Manual Refresh Options

You can force immediate updates:

1. **Clear All Caches**: Admin button to clear all transients
2. **Force Refresh Single Vessel**: `yatco_api_only_get_vessel_data( $token, $vessel_id, true )`
3. **Check Vessel Status**: `yatco_api_only_check_vessel_status( $token, $vessel_id )`

## Configuration

Cache durations can be configured in WordPress admin:
- Settings → YATCO API → API-Only Mode Settings
- Set custom cache durations (in seconds)
- Or use presets (15 min, 1 hour, 6 hours, 24 hours)

## Example Timeline

**Scenario**: A vessel's price changes at 2:00 PM

- **2:00 PM**: Price changes in YATCO system
- **2:00 PM - 3:00 PM**: Cached data still shows old price (if viewed)
- **3:00 PM**: Cache expires (1 hour default)
- **3:00 PM+**: Next view fetches fresh data → new price displayed

**For immediate updates**: Clear cache or set cache duration to 15-30 minutes.

## Storage Impact

- **No storage used** for vessel data (only temporary transients)
- **Transients auto-expire** (no manual cleanup needed)
- **Total storage**: ~50-100MB (vs 10-20GB with CPT approach)

## Performance Impact

- **API calls**: Only when cache expires or on first view
- **Cached requests**: Instant (no API call)
- **Typical page load**: Fast (most data is cached)

## Summary

**Change Detection Frequency:**
- **Removed/Sold vessels**: Every 6 hours (configurable)
- **Price/Status updates**: Every 1 hour (configurable)
- **Days on market**: Always current (from API)

**You can adjust these frequencies** based on your needs:
- More frequent = More up-to-date but more API calls
- Less frequent = Fewer API calls but slightly older data

The plugin automatically handles all change detection - you just set the cache durations that work best for your site!

