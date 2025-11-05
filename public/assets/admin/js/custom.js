$(document).ready(function () {
    hideLoader();
    var baseurl = $('#baseurl').val();
    var pageName = $('#pageName').val();
    $('#logoutClicker').click(function () {
        $("#logout-form").submit();
    });
    $('body').on('click', '.showVideo', function() {
        var videoSrc = $(this).data('video'); // Get video path from button
        var modal = $('#videoModal');

        modal.find('video source').attr('src', videoSrc);
        modal.find('video')[0].load(); // Reload video to apply new source
        modal.find('.modal-title').text($(this).data('title'));
        modal.modal('show');
    });
    $('#videoModal').on('hidden.bs.modal', function () {
        $(this).find('video')[0].pause();
    });
    if (pageName == 'dashboard') {

    }
    if (pageName == 'roles.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_roles",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'users.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_users",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'personal_accounts.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_personal_accounts",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'business_accounts.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_business_accounts",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'chef_accounts.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_chef_accounts",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'sponsored_accounts.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_sponsored_accounts",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'pages.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_pages",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'generickeys.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_generic_keys",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'generickeyvalues.index') {
        function initialize_generic_key_values_datatable(){
            var DTable = $('.dynamicTable').DataTable({
                "processing": true,
                "serverSide": true,
                "bInfo": true,
                "ajax": {
                    url: baseurl + "admin/ajax/get_generic_key_values",
                    'data': function (data) {
                        var key_id = $('#searchFormListing').find('select[name="key_id"]').val();
                        
                        data._token = $('meta[name="csrf-token"]').attr('content');
                        if(key_id!=null && key_id!=undefined && key_id!=''){
                            data.key_id = key_id;
                        }
                    },
                    type: "POST"
                },
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: 'lBfrtip',
                'ordering': false,
                oLanguage: {},
                buttons: []
            });
        }
        initialize_generic_key_values_datatable();
        $('.searchSubmitter').click(function(){
            $('.dynamicTable').DataTable().clear();
            $('.dynamicTable').DataTable().destroy();
            initialize_generic_key_values_datatable();
        }); 
    }
    if (pageName == 'categories.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_categories",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'videos.index') {
        function initialize_videos_datatable(){
            var DTable = $('.dynamicTable').DataTable({
                "processing": true,
                "serverSide": true,
                "bInfo": true,
                "ajax": {
                    url: baseurl + "admin/ajax/get_videos",
                    'data': function (data) {
                        var user = $('#searchFormListing').find('select[name="user"]').val();
                        var video_type = $('#searchFormListing').find('select[name="video_type"]').val();
                        var title = $('#searchFormListing').find('input[name="title"]').val();
                        var is_soft_delete = $('#searchFormListing').find('select[name="is_soft_delete"]').val();
                        
                        data._token = $('meta[name="csrf-token"]').attr('content');
                        if(user!=null && user!=undefined && user!=''){
                            data.user = user;
                        }
                        if(video_type!=null && video_type!=undefined && video_type!=''){
                            data.video_type = video_type;
                        }
                        if(title!=null && title!=undefined && title!=''){
                            data.title = title;
                        }
                        if(is_soft_delete!=null && is_soft_delete!=undefined && is_soft_delete!=''){
                            data.is_soft_delete = is_soft_delete;
                        }
                    },
                    type: "POST"
                },
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: 'lBfrtip',
                'ordering': false,
                oLanguage: {},
                buttons: []
            });
        }
        initialize_videos_datatable();
        $('.searchSubmitter').click(function(){
            $('.dynamicTable').DataTable().clear();
            $('.dynamicTable').DataTable().destroy();
            initialize_videos_datatable();
        });
    }
    if (pageName == 'user_payments.index') {
        function initialize_user_payments_datatable(){
            var DTable = $('.dynamicTable').DataTable({
                "processing": true,
                "serverSide": true,
                "bInfo": true,
                "ajax": {
                    url: baseurl + "admin/ajax/get_user_payments",
                    'data': function (data) {
                        var user = $('#searchFormListing').find('select[name="user"]').val();
                        var payment_for = $('#searchFormListing').find('select[name="payment_for"]').val();
                        var TranId = $('#searchFormListing').find('input[name="TranId"]').val();
                        var start_date = $('#searchFormListing').find('input[name="start_date"]').val();
                        var end_date = $('#searchFormListing').find('input[name="end_date"]').val();
                        
                        data._token = $('meta[name="csrf-token"]').attr('content');
                        if(user!=null && user!=undefined && user!=''){
                            data.user = user;
                        }
                        if(payment_for!=null && payment_for!=undefined && payment_for!=''){
                            data.payment_for = payment_for;
                        }
                        if(TranId!=null && TranId!=undefined && TranId!=''){
                            data.TranId = TranId;
                        }
                        if(start_date!=null && start_date!=undefined && start_date!=''){
                            data.start_date = start_date;
                        }
                        if(end_date!=null && end_date!=undefined && end_date!=''){
                            data.end_date = end_date;
                        }
                    },
                    type: "POST"
                },
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: 'lBfrtip',
                'ordering': false,
                oLanguage: {},
                buttons: []
            });
        }
        initialize_user_payments_datatable();
        $('.searchSubmitter').click(function(){
            $('.dynamicTable').DataTable().clear();
            $('.dynamicTable').DataTable().destroy();
            initialize_user_payments_datatable();
        });
    }
    if (pageName == 'user_reviews.index') {
        function initialize_user_reviews_datatable(){
            var DTable = $('.dynamicTable').DataTable({
                "processing": true,
                "serverSide": true,
                "bInfo": true,
                "ajax": {
                    url: baseurl + "admin/ajax/get_user_reviews",
                    'data': function (data) {
                        var user = $('#searchFormListing').find('select[name="user"]').val();
                        var rating = $('#searchFormListing').find('select[name="rating"]').val();
                        var status = $('#searchFormListing').find('select[name="status"]').val();
                        var start_date = $('#searchFormListing').find('input[name="start_date"]').val();
                        var end_date = $('#searchFormListing').find('input[name="end_date"]').val();
                        
                        data._token = $('meta[name="csrf-token"]').attr('content');
                        if(user!=null && user!=undefined && user!=''){
                            data.user = user;
                        }
                        if(rating!=null && rating!=undefined && rating!=''){
                            data.rating = rating;
                        }
                        if(status!=null && status!=undefined && status!=''){
                            data.status = status;
                        }
                        if(start_date!=null && start_date!=undefined && start_date!=''){
                            data.start_date = start_date;
                        }
                        if(end_date!=null && end_date!=undefined && end_date!=''){
                            data.end_date = end_date;
                        }
                    },
                    type: "POST"
                },
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: 'lBfrtip',
                'ordering': false,
                oLanguage: {},
                buttons: []
            });
        }
        initialize_user_reviews_datatable();
        $('.searchSubmitter').click(function(){
            $('.dynamicTable').DataTable().clear();
            $('.dynamicTable').DataTable().destroy();
            initialize_user_reviews_datatable();
        });
    }
    if (pageName == 'notifications.index') {
        function initialize_notifications_datatable(){
            var DTable = $('.dynamicTable').DataTable({
                "processing": true,
                "serverSide": true,
                "bInfo": true,
                "ajax": {
                    url: baseurl + "admin/ajax/get_notifications",
                    'data': function (data) {
                        data._token = $('meta[name="csrf-token"]').attr('content');
                    },
                    type: "POST"
                },
                "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                dom: 'lBfrtip',
                'ordering': false,
                oLanguage: {},
                buttons: []
            });
        }
        initialize_notifications_datatable();
        $('.searchSubmitter').click(function(){
            $('.dynamicTable').DataTable().clear();
            $('.dynamicTable').DataTable().destroy();
            initialize_notifications_datatable();
        });
        $('body').on('click', '.viewNotificationDetails', function() {
            var title = $(this).data('title');
            var text = $(this).data('text');
            var date = $(this).data('date');
            var modal = $('#notificationModal');
            modal.find('.notification_title').text(title);
            modal.find('.notification_date').text(date);
            modal.find('.notification_text').text(text);
            modal.modal('show');
            
        });
        
    }
    if (pageName == 'banners.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_banners",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'blogcategories.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_blogcategories",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'blogs.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_blogs",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'screens.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_screens",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'works.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_works",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'advertisements.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_advertisements",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'packages.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_packages",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'cities_groups.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_cities_groups",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    if (pageName == 'audios.index') {
        var DTable = $('.dynamicTable').DataTable({
            "processing": true,
            "serverSide": true,
            "bInfo": true,
            "ajax": {
                url: baseurl + "admin/ajax/get_audios",
                'data': function (data) {
                    data._token = $('meta[name="csrf-token"]').attr('content');
                },
                type: "POST"
            },
            "aLengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: 'lBfrtip',
            'ordering': false,
            oLanguage: {},
            buttons: []
        });
    }
    
    $('.copyToAll').click(function () {
        const languageId = this.getAttribute('data-language-id'); // The current language ID to copy from
        const fields = document.querySelectorAll(`#nav-language-${languageId} input:not([type="hidden"]), #nav-language-${languageId} textarea, #nav-language-${languageId} select`);
        let fieldValues = {};

        // Gather field values from the current tab
        fields.forEach(function (field) {
            const baseName = field.name.replace(/\[\d+\]$/, ''); // Extract the base name before the first '['
            if (field.classList.contains('ckeditor-classic')) {
                const editorId = field.getAttribute('id');
                if (editorInstances[editorId]) {
                    fieldValues[baseName] = editorInstances[editorId].getData(); // Get CKEditor content
                }
            } else {
                const fieldValue = field.type === "checkbox" ? field.checked : field.value;
                fieldValues[baseName] = fieldValue; // Store the base name and value
            }
        });

        // Iterate over all other tabs
        document.querySelectorAll('.tab-pane').forEach(function (tab) {
            const currentLanguageId = tab.getAttribute('id').replace('nav-language-', ''); // Extract the current language ID

            if (currentLanguageId !== languageId) { // Skip the source tab
                for (const baseName in fieldValues) {
                    // Construct the field name for the target tab
                    const targetFieldName = `${baseName}[${currentLanguageId}]`;
                    const targetField = tab.querySelector(`[name="${targetFieldName}"]`);

                    if (targetField) {
                        if (targetField.type === "checkbox") {
                            targetField.checked = fieldValues[baseName]; // Copy checkbox value
                        } else if (targetField.classList.contains('ckeditor-classic')) {
                            const editorId = targetField.getAttribute('id');
                            if (editorInstances[editorId]) {
                                editorInstances[editorId].setData(fieldValues[baseName]); // Set CKEditor content
                            } else {
                                // Defer action if editor is not initialized
                                editorDeferredActions[editorId] = fieldValues[baseName];
                            }
                        } else {
                            targetField.value = fieldValues[baseName]; // Copy input/textarea value
                        }
                    }
                }
            }
        });
    });
    $('body').on('change', 'select[name="to_type"]', function() {
        var to_type = $(this).val();
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/get_front_users_list",
            type: 'POST',
            data: {
                to_type: to_type,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    $('select[name="front_user_id"]').empty();
                    var newOption = new Option('All', '', false, false);
                    $('select[name="front_user_id"]').append(newOption).trigger('change');
                    $.each(data.users, function(index, user) {
                        var newOption = new Option(user.name+" - "+user.phone, user.id, false, false);
                        $('select[name="front_user_id"]').append(newOption).trigger('change');
                    });
                }
            }
        });  
    });
    $('body').on('change', 'select[name="country"]', function() {
        var country = $(this).val();
        showLoader();
        if(pageName=='cities_groups.create' || pageName=='cities_groups.edit'){
            $.ajax({
                url: baseurl + "admin/ajax/get_country_cities",
                type: 'POST',
                data: {
                    country: country,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: "json",
                success: function (data) {
                    hideLoader();
                    if(data.status == true){
                        // Detect city select element
                        var $citySelect = $('select[name="city"]');
                        if ($citySelect.length === 0) {
                            $citySelect = $('select[name="cities[]"]');
                        }

                        // Reset and populate cities
                        $citySelect.empty();
                        var newOption = new Option('Select Option', '', false, false);
                        $citySelect.append(newOption).trigger('change');

                        $.each(data.cities, function(index, city) {
                            var newOption = new Option(city.name, city.id, false, false);
                            $citySelect.append(newOption).trigger('change');
                        });
                    }
                }
            });
        }
        else{
            $.ajax({
                url: baseurl + "admin/ajax/get_country_states",
                type: 'POST',
                data: {
                    country: country,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: "json",
                success: function (data) {
                    hideLoader();
                    if(data.status == true){
                        $('select[name="state"]').empty();
                        var newOption = new Option('Select Option', '', false, false);
                        $('select[name="state"]').append(newOption).trigger('change');
                        $.each(data.states, function(index, state) {
                            var newOption = new Option(state.name, state.id, false, false);
                            $('select[name="state"]').append(newOption).trigger('change');
                        });
                    }
                }
            });
        }
    });
    $('body').on('change', 'select[name="state"]', function() {
        var state = $(this).val();
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/get_state_cities",
            type: 'POST',
            data: {
                state: state,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    // Detect city select element
                    var $citySelect = $('select[name="city"]');
                    if ($citySelect.length === 0) {
                        $citySelect = $('select[name="cities[]"]');
                    }

                    // Reset and populate cities
                    $citySelect.empty();
                    var newOption = new Option('Select Option', '', false, false);
                    $citySelect.append(newOption).trigger('change');

                    $.each(data.cities, function(index, city) {
                        var newOption = new Option(city.name, city.id, false, false);
                        $citySelect.append(newOption).trigger('change');
                    });
                }
            }
        });  
    });
    $('body').on('change', '.userStatusChanger', function() {
        var id = $(this).data('id');
        var status = 0;
        if ($(this).is(':checked')) {
            status=1;
        }
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/change_user_status",
            type: 'POST',
            data: {
                id: id,
                status: status,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    displaySuccesMessage(data.message);
                }
            }
        });
    });
    $('body').on('click', '.clearOutstandingBalanceBtn', function() {
        var id = $(this).data('id');
        var total_outstanding_balance = $(this).attr('data-total_outstanding_balance');
        Swal.fire({
            title: 'Are you sure?',
            text: "The outstanding balance for this business account is: " + total_outstanding_balance,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, clear it!'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoader();
                $.ajax({
                    url: baseurl + "admin/ajax/clear_outstanding_balance",
                    type: 'POST',
                    data: {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: "json",
                    success: function (data) {
                        hideLoader();
                        if(data.status == true){
                            displaySuccesMessage(data.message);
                            $('.dynamicTable').DataTable().ajax.reload();
                        }
                    }
                });
            }
        });
    });
    $('body').on('click', '.clearOneTimeQROutstandingBalanceBtn', function() {
        var id = $(this).data('id');
        var one_time_discount_outstanding_balance = $(this).attr('data-one_time_discount_outstanding_balance');
        Swal.fire({
            title: 'Are you sure?',
            text: "The one-time QR reward outstanding balance for this business account is: " + one_time_discount_outstanding_balance,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, clear it!'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoader();
                $.ajax({
                    url: baseurl + "admin/ajax/clear_one_time_qr_outstanding_balance",
                    type: 'POST',
                    data: {
                        id: id,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: "json",
                    success: function (data) {
                        hideLoader();
                        if(data.status == true){
                            displaySuccesMessage(data.message);
                            $('.dynamicTable').DataTable().ajax.reload();
                        }
                    }
                });
            }
        });
    });
    function videoStatusChanger(id, status) {
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/change_video_status",
            type: 'POST',
            data: {
                id: id,
                status: status,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    displaySuccesMessage(data.message);
                }
            }
        });
    }
    $('body').on('change', '.videoStatusChanger', function() {
        var id = $(this).data('id');
        var reports_counter = $(this).attr('data-reports_counter');
        var status = 0;
        if ($(this).is(':checked')) {
            status=1;
        }

        if(status == 1 && reports_counter >= 10){
            Swal.fire({
                title: 'Are you sure?',
                text: "This video was disabled due to high number of reports.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, enable it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    videoStatusChanger(id, status);
                }
            });
        }
        else{
            videoStatusChanger(id, status);
        }
    });
    $('body').on('change', '.userReviewVisibilityChanger', function() {
        var id = $(this).data('id');
        var is_visible = 0;
        if ($(this).is(':checked')) {
            is_visible=1;
        }
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/change_user_review_visibility",
            type: 'POST',
            data: {
                id: id,
                is_visible: is_visible,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    displaySuccesMessage(data.message);
                }
            }
        });  
    });
    $('body').on('click', '.oneTimeQRRewardModalBtn', function() {
        var id = $(this).data('id');
        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/get_business_account_details",
            type: 'POST',
            data: {
                id: id,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    var modal = $('#oneTimeQRRewardModal');
                    modal.find('#business_account_id').val(id);
                    modal.find('#no_of_one_time_discounts').val(data.additional_data?.no_of_one_time_discounts);
                    modal.find('#one_time_max_discount').val(data.additional_data?.one_time_max_discount);
                    
                    if(data.additional_data?.allow_one_time_qr_discount == 1){
                        modal.find('#allow_one_time_qr_discount').prop('checked', true);
                    }
                    else{
                        modal.find('#allow_one_time_qr_discount').prop('checked', false);
                    }

                    modal.modal('show');
                }
            }
        });
    });
    $('body').on('click', '.oneTimeQRRewardSaveBtn', function() {
        var modal = $('#oneTimeQRRewardModal');
        var id = modal.find('#business_account_id').val();
        var no_of_one_time_discounts = modal.find('#no_of_one_time_discounts').val();
        var one_time_max_discount = modal.find('#one_time_max_discount').val();
        var allow_one_time_qr_discount = 0;

        if(modal.find('#allow_one_time_qr_discount').is(':checked')){
            allow_one_time_qr_discount = 1;
        }
        else{
            allow_one_time_qr_discount = 0;
        }

        showLoader();
        $.ajax({
            url: baseurl + "admin/ajax/update_one_time_qr_data",
            type: 'POST',
            data: {
                id: id,
                no_of_one_time_discounts: no_of_one_time_discounts,
                one_time_max_discount: one_time_max_discount,
                allow_one_time_qr_discount: allow_one_time_qr_discount,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: "json",
            success: function (data) {
                hideLoader();
                if(data.status == true){
                    modal.find('#business_account_id').val('');
                    modal.find('#no_of_one_time_discounts').val('');
                    modal.find('#one_time_max_discount').val('');
                    modal.find('#allow_one_time_qr_discount').prop('checked', false);
                    modal.modal('hide');
                    displaySuccesMessage(data.message);
                }
            }
        });
    });
    
    // var lati = $('#latitude').val();
    // var longi = $('#longitude').val();
    // if (lati == '' && longi == '') {
    //     lati = 31.520370;
    //     longi = 74.358747;
    // }
    // $('#us3').locationpicker({
    //     location: {
    //         latitude: lati,
    //         longitude: longi
    //     },
    //     radius: 0,
    //     inputBinding: {
    //         latitudeInput: $('#latitude'),
    //         longitudeInput: $('#longitude'),
    //         radiusInput: $('#us3-radius'),
    //         locationNameInput: $('#us3-address')
    //     },
    //     enableAutocomplete: true,
    //     onchanged: function (currentLocation, radius, isMarkerDropped) {
    //         // Uncomment line below to show alert on each Location Changed event
    //         //alert("Location changed. New location (" + currentLocation.latitude + ", " + currentLocation.longitude + ")");
    //     }
    // });
    // $(".placepicker").placepicker();

    // Advanced usage
    // $("#advanced-placepicker").each(function () {
    //     var target = this;
    //     var $collapse = $(this).parents('.form-group').next('.collapse');
    //     var $map = $collapse.find('.another-map-class');

    //     var placepicker = $(this).placepicker({
    //         map: $map.get(0),
    //         placeChanged: function (place) {
    //             console.log("place changed: ", place.formatted_address, this.getLocation());
    //         }
    //     }).data('placepicker');
    // });


    function displayErrors(errors, is_string = 0) {
        var html = "";
        if(is_string==1){
            html = errors;
        }
        else{
            $.each(errors, function(key, messages) {
                html+=messages[0]+"<br>";
            });
        }
        $('.customtoastifyError').html(html);
        $('.customtoastifyError').show();
        setTimeout(function(){
            $('.customtoastifyError').hide();
        }, 4000);
    }
    function displaySuccesMessage(message){
        var html = message;
        $('.customtoastifySuccess').html(html);
        $('.customtoastifySuccess').show();
        setTimeout(function(){
            $('.customtoastifySuccess').hide();
        }, 4000);
    }
    function showLoader(){
        $('.preloader').fadeIn(500);
    }
    function hideLoader(){
        $('.preloader').fadeOut(500);
    }
    $('body').on('click', 'button.deleteAction', function () {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $(this).closest('form').submit();
            }
        });
    });
    $('.select2').select2();
    $(".datepicker").datepicker();
    $(".monthPicker").datepicker({
        dateFormat: "MM yy",         // Format to display only year and month
        changeMonth: true,           // Show month dropdown
        changeYear: true,            // Show year dropdown
        showButtonPanel: true, 
        onClose: function(dateText, inst) {
            // Handle the selected date format for month/year only
            var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
            var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
            $(this).datepicker('setDate', new Date(year, month, 1));
        }
    }).focus(function() {
        $(".ui-datepicker-calendar").hide();  // Hide calendar to show only month/year
    });
    $('.timepicker').timepicker({
        timeFormat: 'HH:mm',     // 24-hour format
        interval: 60,            // Time interval in minutes
        showButtonPanel: false,  // Hide date controls
        controlType: 'select',   // Use dropdown for hours and minutes
        oneLine: true            // Show in a single line
    });
    $('[data-bs-toggle="tooltip"]').tooltip()

    // Store editor instances as an object
    let editorInstances = {};
    let editorDeferredActions = {};
    // Initialize CKEditor for elements in the current context
    function initializeCKEditor(container) {
        console.log('container', container);
        const ckClassicEditors = container.querySelectorAll(".ckeditor-classic");
        ckClassicEditors.forEach(function (editorElement) {
            const editorId = editorElement.getAttribute('id');
            if (editorId) {
                ClassicEditor.create(editorElement, {
                    toolbar: [
                        'headings',
                        'bold',
                        'italic',
                        'link',
                        'unlink',
                        'bulletedList',
                        'numberedList'
                    ]
                })
                .then(function (editor) {
                    editorInstances[editorId] = editor; // Store editor instance with its ID as the key
                    editor.ui.view.editable.element.style.height = "200px";

                    // Execute deferred actions for this editor if any
                    if (editorDeferredActions[editorId]) {
                        editor.setData(editorDeferredActions[editorId]);
                        delete editorDeferredActions[editorId]; // Clear deferred action
                    }
                })
                .catch(function (error) {
                    console.error(error);
                });
            } else {
                console.error('Editor element missing an ID:', editorElement);
            }
        });
    }
    // Destroy all CKEditor instances
    function destroyEditors() {
        for (const editorId in editorInstances) {
            const editor = editorInstances[editorId];
            if (editor) {
                editor.destroy()
                    .catch(function (error) {
                        console.error('Error destroying editor:', error);
                    });
            }
        }
        editorInstances = {}; // Clear the instances object
    }

    // Event listeners and initialization remain the same
    const activeTab = document.querySelector('.tab-pane.active');
    if (activeTab) {
        initializeCKEditor(activeTab);
    }

    const tabs = document.querySelectorAll('a[data-bs-toggle="tab"]');
    tabs.forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            const targetTabId = event.target.getAttribute('href').substring(1); // Get the target tab ID
            const targetTab = document.getElementById(targetTabId);

            if (targetTab) {
                destroyEditors(); // Destroy all existing editors
                initializeCKEditor(targetTab); // Initialize editors for the newly active tab
            }
        });
    });
    
    // count
    $('.count').each(function () {
        $(this).prop('Counter',0).animate({
            Counter: $(this).text()
        }, {
            duration: 2000,
            easing: 'swing',
            step: function (now) {
                $(this).text(Math.ceil(now));
            }
        });
    });
});