<?php
	namespace Api\Controler;
	class File extends Abstractex {
        public function doChunk() {
            $dir = './upload/'.self::_getDir(time()).'/'.md5($this->toStr('name'));
            if(!file_exists($dir)) {
                $this->doCreateDir($dir);
            }
            $fileStatus = $this->_uploadFile($_FILES['file'], $dir, array(), 0, $this->toInt('index'));
            if(!$fileStatus['status']) {
                $this->_error($fileStatus['info']);
            }
            if(self::_allChunkUploadComplete($dir, $this->toInt('total'))) {
                $source = explode('|',$this->toStr('name'));
                $filePath = self::_combine($dir, self::_getExtension($source[0]), $this->toInt('total'));
                //$fileInfo = array();
                //if($this->toStr('type') == 'image') {
                  //  self::_haveCompress($dir,$filePath);
                    $fileInfo = self::_getInfo($filePath);
                //}
                $fileInfo['filepath'] = $filePath;
                $fileInfo['type'] = 'complete';
                $this->_success($fileInfo);
            }else{
                $this->_success(array('type'=>'chunk'));
            }
        }
        private function _haveCompress($dir, $filePath) {
            //是否存在压缩图片, 如果存在则将源图片名添加big_前缀
            if(file_exists($dir.'.jpg')) {
                rename($filePath, dirname($filePath).'/big_'.basename($filePath));
                rename($dir.'.jpg', $filePath);
            }
        }
        private function _getExtension($file) { 
            return pathinfo($file, PATHINFO_EXTENSION); 
        }
        private function _combine($dir, $ext, $chunkNum) {
            $ext = $ext == '' ? '' : '.'.$ext;
            $fileName = dirname($dir).'/'.time().mt_rand(1000,9999);
            $fp = fopen($fileName.$ext,"ab");
            for($i=0; $i<$chunkNum; $i++) {
                $chunkFile = $dir.'/'.$i;
                $handle = fopen($chunkFile,'r');
                fwrite($fp, fread($handle, filesize($chunkFile)));
                fclose($handle);
                unlink($chunkFile);
            }
            fclose($fp);
            rmdir($dir);
            return $fileName.$ext;
        }
        private function _allChunkUploadComplete($dir, $chunkNum) {
            if(count(scandir($dir)) == $chunkNum+2)
                return 1;
            return 0;
        }
        //断点上传用
        private function _checkChunkUpload() {
            return 1;
        }
		public function doCapture() {
			$image = $this->toStr('image');
            $fileName = $this->toStr('name');
			$image = preg_replace('/^data:image\/\w+;base64,/', '', $image);
            $dir = './upload/'.self::_getDir(time());
        	$file_type = 'jpg';
			if(!file_exists($dir)) {
				$this->doCreateDir($dir);
			}
            if($fileName == '')
			    $filePath = $dir.'/'.time().mt_rand(1000,9999).'.'.$file_type;
            else
                $filePath = $dir.'/'.md5($fileName).'.'.$file_type;
        	file_put_contents($filePath, base64_decode($image));
            $fileInfo = self::_getInfo($filePath);
            $fileInfo['filepath'] = $filePath;
            $fileName == '' && $fileInfo['type'] = 'complete';
			$this->_success($fileInfo);
		}
		public function doAttach() {
		    $dir = './upload/'.self::_getDir(time());
			if(!file_exists($dir)) {
            	$this->doCreateDir($dir);
            }
			$fileInfo = $this->_uploadFile($_FILES['file'], $dir,array(),8*1024*1024);
			echo json_encode($fileInfo);
		}
		public function doDownload() {
		    $desname = $this->toStr('desname');
		    $srcname = $this->toStr('srcname');
		    
		    $time = intval(substr($desname, 0, 10));
		    $file = './upload/'.self::_getDir($time).'/'.$desname;
		    header('Content-type: text/plain');
		    header('Content-Disposition: attachment; filename="'.$srcname.'"');
		    readfile($file);
		}
		public function doCreateDir($dir, $mode=0777) {
			if(!@mkdir($dir, $mode)) {
				$sundir = dirname($dir);
				self::doCreateDir($sundir, $mode);
			}
			@mkdir($dir, $mode);
            @chmod($dir, $mode);
		}
		/**
		 * 获取上传目录
		 */
		private function _getDir($time = 0) {
		    if($time)
		        return date('Ym/d', $time);
		    return date('Ym/d', time());
		}
        static private function _getInfo($path) {
           
            $data = getimagesize($path);
            $imgInfo = array();
            $imgInfo["width"]  = $data[0];
            $imgInfo["height"] = $data[1];
            $imgInfo["type"]   = $data[2];
                                
            return $imgInfo;
        }
		/**
		 * 自定义一个文件上传函数
		 * @param array $upfile 上传文件信息： 如：$_FILES['filename']
		 * @param string $path 上传文件的存储路径： 如："./uploads";
		 * @param array $typelist 表示允许的上传文件类型，默认为空数组表示不限制
		 *	  如：图片类型： array("image/jpeg","image/pjpeg","image/gif","image/png");
		 * @param int $maxsize 表示允许上传文件的大小 默认为0表示不限制
		 * @return array 返回一个关联数组：其中有两个单元：
		 *	  第一个单元：下标status：值为true表示成功，false表示失败
		 *	  第二个单元：下标info：  上传成功值为文件名，失败值为错误信息
		 */
		private function _uploadFile($upfile,$path,$typelist=array(),$maxsize=0,$fileName = ''){
			$path = rtrim($path,"/")."/"; //处理上传路径的右侧斜线。
			//定义返回值信息
			$res=array('status'=>false,'info'=>"");
			
			//1. 判断上传的错误信息
			if($upfile['error']>0){
				switch($upfile['error']){
					case 1: $info="上传文件大小超出PHP配置文件的设置"; break; 
					case 2: $info="上传文件大小超过了表单中MAX_FILE_SIZE指定的值"; break; 
					case 3: $info="文件只有部分被上传。"; break; 
					case 4: $info="没有文件被上传。"; break; 
					case 6: $info="找不到临时文件夹。"; break; 
					case 7: $info="文件写入失败。"; break; 
					default: $info="未知错误"; break;
				}
				$res['info']=$info;
				return $res;
			}


			//2. 过滤上传文件的类型
			if(count($typelist)>0){ //判断是否执行类型过滤
				if(!in_array($upfile['type'],$typelist)){
					$res['info']="上传文件类型错误：".$upfile['type'];
					return $res;
				}
			}

			//3. 过滤上传文件的大小
			if($maxsize>0){
				if($upfile['size']>$maxsize){
					$res['info']="上传文件大小超出了允许范围！".$maxsize;
					return $res;
				}
			}

			//4. 上传文件名处理（随机名字,后缀名不变）
			$extname = pathinfo($upfile['name'],PATHINFO_EXTENSION);
			do{
				//随机一个文件名，格式：时间戳+4位随机数+源后缀名
				$newname = $extname ? time().rand(1000,9999).'.'.$extname : time().rand(1000,9999);
			}while(file_exists($path.$newname)); //判断随机的文件名是否存在。
            if($fileName !== '') $newname = $fileName;
			//5. 判断并执行文件上传。
			if(is_uploaded_file($upfile['tmp_name'])){
				if(move_uploaded_file($upfile['tmp_name'],$path.$newname)){
					$res['info']=$newname;
					$res['status']=true;
				}else{
					$res['info']='执行上传文件移动失败！';
				}
			}else{
				$res['info']='不是一个有效的上传文件！';
			}
			
			return $res;
		}

	}
