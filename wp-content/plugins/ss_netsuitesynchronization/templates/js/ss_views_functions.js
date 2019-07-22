var $ = jQuery;
$(function(){
	$('.ssHeaderNawBlock li span').click(function(e){
		e.preventDefault();
		if (!$(this).hasClass('active'))
		{
			$('.ssHeaderNawBlock li span').removeClass('active');
			$(this).addClass('active');
			var openSectionId = $(this).data('section-id');
			openSection(openSectionId);
		}
	});
	if ($('.ssHeaderNawBlock li span.active').size() > 0)
	{
		var openSectionId = $('.ssHeaderNawBlock li span.active').data('section-id');
		openSection(openSectionId);
	}
});



function openSection(sectionId)
{
	$('.ssSection').hide();
	$('#'+sectionId).show();
}

$(document).ready(function()
{
    /*
    $('.user_name_box').change(function(){
        $('.show_category_adm').show();
    });
    */
});