<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Kỷ luật</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index.php?p=index&a=statistic">Tổng quan</a></li>
              <li class="breadcrumb-item"><a href="ky-luat.php?p=bonus-discipline&a=discipline">Kỷ luật</a></li>
              <li class="breadcrumb-item active">Kỷ luật nhân viên</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card card-primary card-outline">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas fa-list"></i>
                  Thao tác chức năng
                </h3>
              </div>
              <div class="card-body">
                <a href="ky-luat.php?p=bonus-discipline&a=discipline&tao-loai" class="btn btn-primary">
                  <i class="fas fa-plus"></i> Loại kỷ luật
                </a>
                <a href="ky-luat.php?p=bonus-discipline&a=discipline&ky-luat" class="btn btn-primary">
                  <i class="fas fa-user"></i> Kỷ luật nhân viên
                </a>
                <?php 
                  if(isset($_GET['tao-loai']) || isset($_GET['ky-luat']))
                    echo "<a href='ky-luat.php?p=bonus-discipline&a=discipline' class='btn btn-danger'>
                            <i class='fas fa-sign-out-alt'></i> Trở về
                          </a>";
                ?>
              </div>
            </div>
          </div>
          
          <?php if(isset($_GET['tao-loai'])) { ?>
          <!-- Tạo loại kỷ luật card -->
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas fa-plus-circle"></i>
                  Tạo loại kỷ luật
                </h3>
              </div>
              <div class="card-body">
                <?php 
                  // show error
                  if($row_acc['user_quyen'] != 1) 
                  {
                    echo "<div class='alert alert-warning alert-dismissible'>";
                    echo "<h4><i class='icon fa fa-ban'></i> Thông báo!</h4>";
                    echo "Bạn <b> không có quyền </b> thực hiện chức năng này.";
                    echo "</div>";
                  }
                ?>

                <?php 
                  // show error
                  if(isset($error)) 
                  {
                    if($showMess == false)
                    {
                      echo "<div class='alert alert-danger alert-dismissible'>";
                      echo "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>";
                      echo "<h4><i class='icon fa fa-ban'></i> Lỗi!</h4>";
                      foreach ($error as $err) 
                      {
                        echo $err . "<br/>";
                      }
                      echo "</div>";
                    }
                  }
                ?>
                <?php 
                  // show success
                  if(isset($success)) 
                  {
                    if($showMess == true)
                    {
                      echo "<div class='alert alert-success alert-dismissible'>";
                      echo "<h4><i class='icon fa fa-check'></i> Thành công!</h4>";
                      foreach ($success as $suc) 
                      {
                        echo $suc . "<br/>";
                      }
                      echo "</div>";
                    }
                  }
                ?>
                <form action="" method="POST">
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                        <label for="maLoai" class="form-label">Mã loại</label>
                        <input type="text" class="form-control bg-light" id="maLoai" name="speacialCode" value="<?php echo $maLoai; ?>" readonly>
                      </div>
                      <div class="form-group">
                        <label for="tenLoai" class="form-label">Tên loại <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tenLoai" placeholder="Nhập tên loại" name="tenLoai">
                      </div>
                      <div class="form-group">
                        <label for="moTa" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="moTa" rows="3" name="moTa" placeholder="Nhập mô tả"></textarea>
                      </div>
                      <div class="form-group">
                        <label for="nguoiTao" class="form-label">Người tạo</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-user"></i></span>
                          <input type="text" class="form-control" id="nguoiTao" value="<?php echo $row_acc['ten_nv']; ?>" name="nguoiTao" readonly>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="ngayTao" class="form-label">Ngày tạo</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                          <input type="text" class="form-control" id="ngayTao" value="<?php echo date('d-m-Y H:i:s'); ?>" name="ngayTao" readonly>
                    </div>
                    <!-- /.form-group -->
                    <?php 
                      if($_SESSION['level'] == 1)
                        echo "<button type='submit' class='btn btn-primary' name='taoLoai'><i class='fa fa-plus'></i> Tạo loại kỷ luật</button>";
                    ?>
                  </div>
                  <!-- /.col -->
                </div>
                <!-- /.row -->
                </div>
              </form>
            </div>
            <!-- /.box-body -->
          </div>
          <?php 
          }
          ?>
          </div>
          <!-- /.box -->
          
          <?php 
        //   if(isset($_GET['ky-luat']))   
          { ?>
          <!-- Tạo kỷ luật card -->
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">
                  <i class="fas fa-user-minus"></i>
                  Kỷ luật nhân viên
                </h3>
              </div>
              <div class="card-body">
                <?php 
                  // show error
                  if($row_acc['user_quyen'] != 1) 
                  {
                    echo "<div class='alert alert-warning alert-dismissible'>";
                    echo "<h4><i class='icon fa fa-ban'></i> Thông báo!</h4>";
                    echo "Bạn <b> không có quyền </b> thực hiện chức năng này.";
                    echo "</div>";
                  }
                ?>

                <?php 
                  // show error
                  if(isset($error)) 
                  {
                    if($showMess == false)
                    {
                      echo "<div class='alert alert-danger alert-dismissible'>";
                      echo "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>";
                      echo "<h4><i class='icon fa fa-ban'></i> Lỗi!</h4>";
                      foreach ($error as $err) 
                      {
                        echo $err . "<br/>";
                      }
                      echo "</div>";
                    }
                  }
                ?>
                <?php 
                  // show success
                  if(isset($success)) 
                  {
                    if($showMess == true)
                    {
                      echo "<div class='alert alert-success alert-dismissible'>";
                      echo "<h4><i class='icon fa fa-check'></i> Thành công!</h4>";
                      foreach ($success as $suc) 
                      {
                        echo $suc . "<br/>";
                      }
                      echo "</div>";
                    }
                  }
                ?>
                <form action="" method="POST">
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group mb-3">
                        <label for="maKyLuat" class="form-label">Mã kỷ luật</label>
                        <input type="text" class="form-control bg-light" id="maKyLuat" name="maKyLuat" value="<?php echo $maKyLuat; ?>" readonly>
                      </div>
                      <div class="form-group mb-3">
                        <label for="soQuyetDinh" class="form-label">Số quyết định <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="soQuyetDinh" placeholder="Nhập số quyết định" name="soQuyetDinh" value="<?php echo isset($_POST['soQuyetDinh']) ? $_POST['soQuyetDinh'] : ''; ?>">
                      </div>
                      <div class="form-group mb-3">
                        <label for="ngayQuyetDinh" class="form-label">Ngày quyết định</label>
                        <input type="date" class="form-control" id="ngayQuyetDinh" value="<?php echo date('Y-m-d'); ?>" name="ngayQuyetDinh">
                      </div>
                      <div class="form-group mb-3">
                        <label for="tenKyLuat" class="form-label">Tên kỷ luật <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tenKyLuat" placeholder="Nhập tên kỷ luật" name="tenKyLuat">
                      </div>
                      <div class="form-group mb-3">
                        <label for="nhanVien" class="form-label">Chọn nhân viên: </label>
                        <select class="form-select" id="nhanVien" name="nhanVien">
                        <option value="chon">--- Chọn nhân viên ---</option>
                        <?php 
                          foreach($arrNV as $nv)
                          {
                            echo "<option value='".$nv['id']."'>".$nv['ma_nv']." - ".$nv['ten_nv']."</option>";
                          }
                        ?>
                        </select>
                      </div>
                      <div class="form-group mb-3">
                        <label for="loaiKyLuat" class="form-label">Loại kỷ luật: </label>
                        <select class="form-select" id="loaiKyLuat" name="loaiKyLuat">
                        <option value="chon">--- Chọn kỷ luật ---</option>
                        <?php 
                          foreach($arrShow as $arrS)
                          {
                            echo "<option value='".$arrS['id']."'>".$arrS['ten_loai']."</option>";
                          }
                        ?>
                        </select>
                      </div>
                      <div class="form-group mb-3">
                        <label for="hinhThuc" class="form-label">Hình thức</label>
                        <select class="form-select" id="hinhThuc" name="hinhThuc">
                          <option value="chon">--- Chọn hình thức ---</option>
                          <option value="1">Trừ tiền qua thẻ</option>
                          <option value="0">Trừ tiền mặt</option>
                        </select>
                      </div>
                      <div class="form-group mb-3">
                        <label for="soTienPhat" class="form-label">Số tiền phạt <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="soTienPhat" placeholder="Nhập số tiền phạt" name="soTienPhat" value="<?php echo isset($_POST['soTienPhat']) ? $_POST['soTienPhat'] : ''; ?>">
                      </div>
                      <div class="form-group mb-3">
                        <label for="moTa" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="moTa" name="moTa" rows="5"></textarea>
                      </div>
                      <div class="form-group mb-3">
                        <label for="nguoiTao" class="form-label">Người tạo</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-user"></i></span>
                          <input type="text" class="form-control bg-light" id="nguoiTao" value="<?php echo $row_acc['ten_nv']; ?>" name="nguoiTao" readonly>
                        </div>
                      </div>
                      <div class="form-group">
                        <label for="ngayTao">Ngày tạo</label>
                        <input type="text" class="form-control" id="ngayTao" value="<?php echo date('d-m-Y H:i:s'); ?>" name="ngayTao" readonly>
                      </div>
                      <!-- /.form-group -->
                      <?php 
                        if($_SESSION['level'] == 1)
                          echo "<button type='submit' class='btn btn-primary' name='taoKyLuat'><i class='fa fa-check'></i> Tiến hành kỷ luật</button>";
                      ?>
                    </div>
                    <!-- /.col -->
                  </div>
                  <!-- /.row -->
                </form>
              </div>
              <!-- /.box-body -->
            </div>
            
        </div>
            
            <?php 
            // if(isset($_GET['tao-loai']))
            {
            ?>
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Danh sách loại kỷ luật</h3>
              </div>
              <div class="card-body">
                <?php 
                  // show error
                  if($row_acc['user_quyen'] != 1) 
                  {
                    echo "<div class='alert alert-warning alert-dismissible fade show'>";
                    echo "<h5><i class='icon fas fa-ban'></i> Thông báo!</h5>";
                    echo "Bạn <b> không có quyền </b> thực hiện chức năng này.";
                    echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
                    echo "</div>";
                  }
                ?>

                <?php 
                  // show error
                  if(isset($error)) 
                  {
                    if($showMess == false)
                    {
                      echo "<div class='alert alert-danger alert-dismissible fade show'>";
                      echo "<h5><i class='icon fas fa-ban'></i> Lỗi!</h5>";
                      foreach ($error as $err) 
                      {
                        echo $err . "<br/>";
                      }
                      echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
                      echo "</div>";
                    }
                  }
                ?>
                <?php 
                  // show success
                  if(isset($success)) 
                  {
                    if($showMess == true)
                    {
                      echo "<div class='alert alert-success alert-dismissible fade show'>";
                      echo "<h5><i class='icon fas fa-check'></i> Thành công!</h5>";
                      foreach ($success as $suc) 
                      {
                        echo $suc . "<br/>";
                      }
                      echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
                      echo "</div>";
                    }
                  }
                ?>
                <div class="table-responsive">
                  <table id="example1" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                      <th>STT</th>
                      <th>Mã loại</th>
                      <th>Tên loại</th>
                      <th>Mô tả</th>
                      <th>Người tạo</th>
                      <th>Ngày tạo</th>
                      <th>Người sửa</th>
                      <th>Ngày sửa</th>
                      <th>Sửa</th>
                      <th>Xóa</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php 
                      $count = 1;
                      foreach ($arrShow as $arrS) 
                      {
                    ?>
                        <tr>
                          <td><?php echo $count; ?></td>
                          <td><?php echo $arrS['ma_loai']; ?></td>
                          <td><?php echo $arrS['ten_loai']; ?></td>
                          <td><?php echo $arrS['ghi_chu']; ?></td>
                          <td><?php echo $arrS['nguoi_tao']; ?></td>
                          <td><?php echo $arrS['ngay_tao']; ?></td>
                          <td><?php echo $arrS['nguoi_sua']; ?></td>
                          <td><?php echo $arrS['ngay_sua']; ?></td>
                          <th>
                            <?php 
                              if($row_acc['user_quyen'] == 1)
                              {
                                echo "<form method='POST'>";
                                echo "<input type='hidden' value='".$arrS['id']."' name='idLoai'/>";
                                echo "<button type='submit' class='btn btn-warning btn-sm' name='suaLoai'><i class='fas fa-edit'></i></button>";
                                echo "</form>";
                              }
                              else
                              {
                                echo "<button type='button' class='btn btn-warning btn-sm' disabled><i class='fas fa-edit'></i></button>";
                              }
                            ?>
                          </th>
                          <th>
                            <?php 
                              if($row_acc['user_quyen'] == 1)
                              {
                                echo "<button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#exampleModal' data-bs-whatever='".$arrS['ma_loai']."'><i class='fas fa-trash'></i></button>";
                              }
                              else
                              {
                                echo "<button type='button' class='btn btn-danger btn-sm' disabled><i class='fas fa-trash'></i></button>";
                              }
                            ?>
                          </th>
                        </tr>
                    <?php
                        $count++;
                      }
                    ?>
                    </tbody>
                  </table>
                </div>
            </div>  
            </div>
              <?php }  ?>
            <!-- Bảng kỷ luật -->
           <?php }  ?>
           </div>  
             <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Danh sách kỷ luật</h3>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table id="example1" class="table table-bordered table-striped">
                      <thead>
                      <tr>
                        <th>STT</th>
                        <th>Mã kỷ luật</th>
                        <th>Tên kỷ luật</th>
                        <th>Tên nhân viên</th>
                        <th>Số quyết định</th>
                        <th>Ngày quyết định</th>
                        <th>Tên loại</th>
                        <th>Hình thức</th>
                        <th>Số tiền</th>
                        <th>Ngày kỷ luật</th>
                        <th>Sửa</th>
                        <th>Xóa</th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php 
                        $count = 1;
                        foreach ($arrKT as $kt) 
                        {
                      ?>
                          <tr>
                            <td><?php echo $count; ?></td>
                            <td><?php echo $kt['ma_kt']; ?></td>
                            <td><?php echo $kt['ten_khen_thuong']; ?></td>
                            <td><?php echo $kt['ten_nv']; ?></td>
                            <td><?php echo $kt['so_qd']; ?></td>
                            <td><?php echo date_format(date_create($kt['ngay_qd']), "d-m-Y"); ?></td>
                            <td><?php echo $kt['ten_loai']; ?></td>
                            <td>
                            <?php 
                              if($kt['hinh_thuc'] == 1)
                              {
                                echo "Trừ tiền qua thẻ";
                              }
                              else
                              {
                                echo "Trừ tiền mặt";
                              }
                            ?>
                            </td>
                            <td><?php echo "<span class='text-danger fw-bold'>". number_format($kt['so_tien'])."vnđ </span>"; ?></td>
                            <td><?php echo date_format(date_create($kt['ngay_tao']), "d-m-Y"); ?></td>
                            <th>
                              <?php 
                                if($row_acc['user_quyen'] == 1)
                                {
                                  echo "<form method='POST'>";
                                  echo "<input type='hidden' value='".$kt['ma_kt']."' name='idKyLuat'/>";
                                  echo "<button type='submit' class='btn btn-warning btn-sm' name='suaKyLuat'><i class='fas fa-edit'></i></button>";
                                  echo "</form>";
                                }
                                else
                                {
                                  echo "<button type='button' class='btn btn-warning btn-sm' disabled><i class='fas fa-edit'></i></button>";
                                }
                              ?>
                            </th>
                            <th>
                              <?php 
                                if($row_acc['user_quyen'] == 1)
                                {
                                  echo "<button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#exampleModal' data-bs-whatever='".$kt['ma_kt']."'><i class='fas fa-trash'></i></button>";
                                }
                                else
                                {
                                  echo "<button type='button' class='btn btn-danger btn-sm' disabled><i class='fas fa-trash'></i></button>";
                                }
                              ?>
                            </th>
                          </tr>
                      <?php
                          $count++;
                        }
                      ?>
                      </tbody>
                    </table>
                  </div>
                </div>
             </div>
            
            
            
            <!-- /.col -->
          
          <!-- /.row -->
        </section>
        <!-- /.content -->
     </div>