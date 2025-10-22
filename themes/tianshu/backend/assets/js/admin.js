( function( $ ) {
    "use strict";

    $(document).ready(function($) {
        // Lắng nghe sự kiện AJAX trả về danh sách icon
        $(document).on('csf-get-icons', function(event, response) {
            if (response.success && response.data.content) {
                // Sử dụng jQuery để loại bỏ icon không mong muốn
                const icons = $(response.data.content);
                console.log(icons)
                // icons.find('i.fab.fa-acquisitions-incorporated').remove(); // Loại bỏ icon "fab fa-acquisitions-incorporated"
                //
                // // Thêm icon mới nếu cần
                // const newIcon = '<i title="New Icon" class="fas fa-heart"></i>';
                // icons.append(newIcon);
                //
                // // Gắn lại nội dung đã sửa đổi
                // $('#icon-container').html(icons); // Hoặc bất kỳ container nào bạn đang sử dụng
            }
        });
    });

} )( jQuery );

