jQuery(document).ready(function($){

	// Populate subjects list through ajax call
	$("#SA_category_list").change(function() {
		populateList(true);
	});
	
	// Add subjects 
	$("#SA_add_search_string").live('click', function(){
		$("#SA_news_search_string_error").hide();
	    if(!$("#SA_new_search_string_name").val() || !$("#SA_new_search_string_name").val()) {
	    	$("#SA_news_search_string_error").show();
            return false;
	    }
		$("#SA_search_strings_empty").remove();
		name = $("#SA_new_search_string_name").val();
		value = $("#SA_new_search_string_value").val();
		position = $("#SA_selected_search_strings").children().length;
		$("#SA_selected_search_strings").append(
			'<li>'
			+name
			+'<a href="#" class="SA_remove_search_string deletion submitdelete delete"><img src="images/xit.gif" alt="" /></a>'
			+'<br/>'
			+'<span class="description">'+value+'</span>'
			+'<input type="hidden" name="SA_search_strings['+position+'][name]" value="'+name+'" />'
			+'<input type="hidden" name="SA_search_strings['+position+'][value]" value="'+value+'" />'
			+'</li>'); 
		$("#SA_new_search_string_name").val('');
		$("#SA_new_search_string_value").val('');
		$("#SA_selected_search_strings").scrollTop($("#SA_selected_search_strings").innerHeight()+2);
		return false;
	});
	
	$(".SA_remove_search_string").live('click', function(){
		$(this).parent().remove();
		populateList(false);
		return false;
	});
	
	function populateList(resetScroll) {
	    exclude = new Array();
	    $("#SA_selected_subjects input[name=SA_selected_subjects]").each(function() {
	    	exclude[exclude.length] = $("#SA_category_list").val();
	    });
	    excludeStr = exclude.join(",");
	    $("#SA_add_subject_list").load("?springest_action=get_subjects&category_id="+$("#SA_category_list").val()+"&exclude="+excludeStr, function() {
	        if(resetScroll) {
                $("#SA_add_subject_list").scrollTop(0);
            }
	    });
	}
	 
	$("#SA_new_search_string_value").autocomplete({
		source: function(request, response) {
		    searchTerm = extractLast(request.term);
		    apiURL = $("#springest_api_url").val();
			$.ajax(apiURL+"/autocomplete/"+escape(searchTerm)+".jsonp", {
				dataType: "jsonp",
				data: {
					api_key: "public",
				},
				success: function(data) {
				   //var re = new RegExp(searchTerm,"g");
                   response($.map(data.results.subjects, function(item) {
                       return {
                           //label: item.name.replace(re, '<strong>'+searchTerm+'</strong>'),
                           label: item.name.replace(/&amp;/, "&"),
                           value: item.name.replace(/&amp;/, "&")
                       }
                   }));
               }
			});
		},
		focus: function() {
			// prevent value inserted on focus
			return false;
		},
		select: function(event, ui) {
			var terms = split(this.value);
			// remove the current input
			terms.pop();
			// add the selected item
			terms.push(ui.item.value);
			// add placeholder to get the comma-and-space at the end
			terms.push( "" );
			this.value = terms.join( ", " );
			return false;
		},
		highlight: true
		
    });
    
    function split(val) {
		return val.split(/,\s*/);
	}
	function extractLast(term) {
		return split(term).pop();
	}
		
});