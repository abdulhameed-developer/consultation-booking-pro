/**
 * Availability Checker Utility
 * Handles logic for calculating date offsets and checking slot conflicts
 */

const CBPAvailability = {
    /**
     * Local YYYY-MM-DD (avoids UTC shift from toISOString).
     */
    toLocalISODate: (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    },

    /**
     * Get next 7 available dates starting from tomorrow
     */
    getDisplayDates: () => {
        const dates = [];
        const now = new Date();
        for (let i = 1; i <= 7; i++) {
            const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
            dates.push({
                iso: CBPAvailability.toLocalISODate(d),
                day: d.toLocaleDateString('en-US', { weekday: 'short' }),
                num: d.getDate(),
                label: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
            });
        }
        return dates;
    },

    /**
     * Check if a time string is in the past for today's date
     */
    isPast: (dateStr, timeStr) => {
        const now = new Date();
        const today = CBPAvailability.toLocalISODate(now);
        if (dateStr > today) return false;
        if (dateStr < today) return true;
        
        const [h, m] = timeStr.split(':');
        const slotTime = new Date();
        slotTime.setHours(parseInt(h, 10), parseInt(m, 10) || 0, 0, 0);
        
        return slotTime < now;
    }
};

if (typeof module !== 'undefined') {
    module.exports = CBPAvailability;
}
