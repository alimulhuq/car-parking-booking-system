document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const defaultDate = `${yyyy}-${mm}-${dd}`;
    
    const checkinInput = document.getElementById('checkin');
    if(checkinInput && !checkinInput.value) {
        checkinInput.value = defaultDate;
    }
    
    // optional: simple validation before submit (still minimal)
    const searchForm = document.getElementById('searchForm');
    if(searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const location = document.getElementById('locationInput').value.trim();
            if(location === '') {
                e.preventDefault();
                alert('Please enter a location');
            }
        });
    }
});