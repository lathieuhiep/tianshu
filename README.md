# WPBuilderKit - WordPress Theme & Plugin Development

## Mô tả
Dự án này bao gồm một theme WordPress cơ bản và một plugin mở rộng các thành phần cho theme (addons thêm cho elementor,...). Bạn có thể phát triển song song cả theme và plugin, quản lý SCSS và JS chung thông qua Gulp.

## Cấu trúc thư mục
- `themes/`: Chứa các theme của WordPress.
- `plugins/`: Chứa các plugin của WordPress.
- `src/`: Thư mục chứa mã nguồn chung cho SCSS và JS.
    - `scss/`: SCSS cho theme và plugin.
    - `js/`: JavaScript cho theme và plugin.
- `gulpfile.js`: File cấu hình Gulp để build và watch.
- `node_modules/`: Các thư viện Node.js.

## Cài đặt và phát triển
1. Clone dự án về:
- Di chuyển vào thư mục wp-content và khởi tạo Git (nếu chưa làm):
    ```bash
    git init
  
2. Thêm remote repository
     ```bash
   git remote add origin <repository_url>
   git pull origin main
   
3. Di chuyển các thư mục themes và plugins tạm thời nếu chúng có dữ liệu từ trước:
    ```bash
    mv themes themes_backup
    mv plugins plugins_backup
   
4. Sao chép lại các tệp từ các thư mục backup vào:
- Sao chép lại các thay đổi từ thư mục themes_backup và plugins_backup vào thư mục tương ứng:
    ```bash
    cp -r themes_backup/* themes/
    cp -r plugins_backup/* plugins/

5. Xóa các thư mục backup tạm thời:
- Sau khi sao chép xong, bạn có thể xóa các thư mục backup:
    ```bash
    rm -rf themes_backup
    rm -rf plugins_backup

## Liên Hệ

Nếu bạn gặp vấn đề hoặc có câu hỏi nào liên quan đến dự án, vui lòng mở issue trên GitHub hoặc liên hệ qua
- email: [khacdiepkma90@gmail.com](mailto:khacdiepkma90@gmail.com)