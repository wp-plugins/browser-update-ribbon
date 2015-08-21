function renderMediaUploader( $ ) {
    'use strict';
 
    var file_frame, image_data, json;

    if ( undefined !== file_frame ) {
        file_frame.open();
        return;
    }
    
    file_frame = wp.media.frames.file_frame = wp.media({
        title: bur.wp_media_title,
        library : {
          type : 'image',
        },
        multiple: false
    });
 
    file_frame.on( 'select', function() {
        json = file_frame.state().get( 'selection' ).first().toJSON();
        if ( 0 > $.trim( json.url.length ) ) { return; }
        $( '#custom-ribbon-image-container' )
            .children( 'img' )
                .attr( 'src', json.sizes.thumbnail.url )
                .attr( 'alt', json.caption )
                .attr( 'title', json.title )
                            .show()
            .parent()
            .removeClass( 'hidden' );
        $( '#browser_update_ribbon_custom_img' ).val( json.url );
        $( '#browser_update_ribbon_custom_img_thumb' ).val( json.sizes.thumbnail.url );
        $("input[name=browser_update_ribbon_ribbon][value='default']").prop('checked',false);
        $("input[name=browser_update_ribbon_ribbon][value='custom']").prop('checked',true);
    });
    file_frame.open();
}
 
(function( $ ) {
    'use strict';
 
    $(function() {
        $( '#imgsel' ).on( 'click', function( evt ) {
            evt.preventDefault();
            renderMediaUploader( $ );
        });
    });
})( jQuery );