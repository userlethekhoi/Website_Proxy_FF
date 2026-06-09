from mitmproxy import http
import os

# Cấu hình đường dẫn thực tế trên máy tính của bạn
# Sử dụng 'r' trước chuỗi để xử lý các ký tự đặc biệt như dấu gạch chéo và dấu ~
PATH_FILEINFO = r"D:\proxynew\fileinfo"
PATH_MOD_FILE = r"D:\proxynew\assetindexer.z4c5PKvI2Gj~2BSdP~2BeRekPvzPvXY~3D"

def request(flow: http.HTTPFlow) -> None:
    # 1. Ghi đè fileinfo khi game yêu cầu kiểm tra danh sách tài nguyên
    if "fileinfo" in flow.request.pretty_url:
        if os.path.exists(PATH_FILEINFO):
            with open(PATH_FILEINFO, "rb") as f:
                flow.response = http.Response.make(
                    200,
                    f.read(),
                    {"Content-Type": "text/plain"}
                )
            print(">>> Đã nạp fileinfo tùy chỉnh thành công!")
        else:
            print(f"!!! LỖI: Không tìm thấy file tại {PATH_FILEINFO}")

    # 2. Ghi đè file cache_res bằng file mod thực tế của bạn
    # Game sẽ tìm theo tên hoặc mã hash cũ trong URL
    elif "assetindexer" in flow.request.pretty_url or "z4c5PKvI2Gj~2BSdP~2BeRekPvzPvXY~3D" in flow.request.pretty_url:
        if os.path.exists(PATH_MOD_FILE):
            with open(PATH_MOD_FILE, "rb") as f:
                flow.response = http.Response.make(
                    200,
                    f.read(),
                    {"Content-Type": "application/octet-stream"}
                )
            print(">>> Đã ghi đè file mod cache_res thành công!")
        else:
            print(f"!!! LỖI: Không tìm thấy file mod tại {PATH_MOD_FILE}")