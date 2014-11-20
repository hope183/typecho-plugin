<?php

/**
 * 显示文章缩略图
 * 
 * @package Thumbnail
 * @author CaiXin Liao <king.liao@qq.com>
 * @version 1.0.0
 * @update: 2014.11.18
 * @link http://blog.molibei.com
 * @useage    Thumbnail_Plugin::show($this->cid,'100'); 
 */
class Thumbnail_Plugin implements Typecho_Plugin_Interface {

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        return _t('可以自定义配置缩略图模版');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
        
    }

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $thumb_theme = new Typecho_Widget_Helper_Form_Element_Text('theme', NULL, NULL, _t('显示模版'), _t('在页面显示的标签'));
        $thumb_theme->value('<img src="$url" width="$width" height="$height" class="thumbnail" alt="$alt">');
        $form->addInput($thumb_theme);
    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {
        
    }

    /**
     * 显示文章第一张图片的缩略图
     * 
     * @access public
     * @param type $src     图片地址 或 文章id
     * @param type $size    100 或 100x 或 x100 或 100x100
     * @param type $crop    1 或 0
     * @author CaiXin Liao <king.liao@qq.com>
     */
    public static function show($src, $size = 100, $crop = FALSE) {
        $mime = array('image/png', 'image/jpeg', 'image/gif', 'image/bmp');
        //渲染模版
        $theme = Typecho_Widget::widget('Widget_Options')->plugin("Thumbnail")->theme;
        $options = Typecho_Widget::widget('Widget_Options');
        //来源地址
        $image = array();
        if (FALSE != $image_info = self::fetchImageInfo($src)) {
            $ext = pathinfo($image_info['path']);
            $file_name = $ext['dirname'] . "/" . $ext['filename'] . "_" . $size . "_" . md5($image_info['name'] . $size) . "." . $ext['extension'];
            if (file_exists(__TYPECHO_ROOT_DIR__ . $file_name)) {
                $info = self::fetchImageInfo($file_name);
                $image['url'] = $options->siteUrl . substr($file_name, 1);
                $image['width'] = $info['width'];
                $image['height'] = $info['width'];
                $image['alt'] = $image_info['description'] ? : $image_info['name'];
            } elseif (self::checkImageMime($image_info, $mime)) {
                $image_info['save_path'] = $file_name;
                $image = self::createThumb($image_info, $size, $crop);
                $image['url'] = $options->siteUrl . $image['url'];
            }
        }
        self::bluidImageTag($theme, $image);
    }

    /**
     * 渲染自定义模版
     * 
     * @param type $theme   模版
     * @param type $image   缩略图
     */
    public function bluidImageTag($theme, $image) {
        $view = "";
        if (count($image) > 0) {
            $view = preg_replace_callback('/\{(\w+)}/iU', function($matches) use($image) {
                return $image[$matches[1]];
            }, $theme);
        }
        echo $view;
    }

    /**
     * 获取图像信息
     * 
     * @access public
     * @param type  $src
     * @return array $img
     */
    public function fetchImageInfo($src) {
        if (is_numeric($src)) {
            return self::fetchAttachment($src);
        }
        $first = substr($src, 0, 1);
        if ('.' == $first) {
            $src = __TYPECHO_ROOT_DIR__ . substr($src, 1);
        } elseif ('/' == $first) {
            $src = __TYPECHO_ROOT_DIR__ . $src;
        } else {
            $src = __TYPECHO_ROOT_DIR__ . DIRECTORY_SEPARATOR . $src;
        }
        if (!file_exists($src)) {
            return FALSE;
        }
        $info = getimagesize($src);
        if ($info === FALSE) {
            return FALSE;
        }
        $file_info = explode('/', $src);
        return array(
            'mime' => $info['mime'],
            'width' => $info[0],
            'height' => $info[1],
            'path' => $src,
            'name' => end($file_info)
        );
    }

    /**
     * 检测图像是否被支持
     * 
     * 
     * @param array $info 图像信息
     * @return boolean 
     */
    public function checkImageMime($info, $mime) {
        return array_key_exists($info['mime'], array_flip($mime));
    }

    /**
     * 从数据库中读取第一张图片
     * 
     * @access private
     * @param type $cid 文章id
     * @return boolean or array $info 图像信息
     */
    private function fetchAttachment($cid) {
        $db = Typecho_Db::get();
        $attachs = $db->fetchAll($db->select("title,slug,text")->from('table.contents')
                        ->where('type = ? AND parent = ? ', 'attachment', (int) $cid)
                        ->order('order')
        );
        if (empty($attachs)) {
            return FALSE;
        }
        $info = array();
        foreach ($attachs as $val) {
            $text = unserialize($val['text']);
            if ($text['isImage']) {
                $info = $text;
            }break;
        }
        return $info;
    }

    /**
     * 计算缩略图尺寸
     * 
     * @param array $data
     * @param string $size
     * @return boolean
     */
    public function calcImagethumbSize($data, $size) {
        $position = strpos(strtolower($size), 'x');
        //计算比例
        if ($position === FALSE && !is_numeric($size)) {
            return FALSE;
        }
        $ratio = $data['width'] / $data['height'];
        if ($position !== FALSE) {
            $width = (int) substr($size, 0, $position);
            $height = (int) substr($size, $position + 1);
        }
        if (is_numeric($size)) {
            $width = (int) $size;
            $height = round(intval($size) / $ratio);
        }
        $crop = FALSE;
        if ($width > 1 && $height > 1 && $width < $data['width'] && $height < $data['height']) {
            $crop = ($width / $height) !== $ratio ? : FALSE;
        }
        if ($width < 1 && $height < 1) {
            return FALSE;
        }
        if ($width > $data['width'] || $height > $data['height']) {
            return FALSE;
        }
        if ($width < 1) {
            return array('height' => $height, 'width' => round($height * $ratio));
        }
        if ($height < 1) {
            return array('height' => round($width / $ratio), 'width' => $width);
        }
        return array('height' => $height, 'width' => $width, 'crop' => $crop);
    }

    /**
     * 处理缩略图
     * 
     * @param type $data
     * @param type $size
     * @return type
     */
    public function createThumb(&$data, $size, $crop) {
        $thumb_size = self::calcImagethumbSize($data, $size);
        //无正确尺寸 返回原图
        $image = array(
            'url' => $data['path'],
            'width' => $data['width'],
            'height' => $data['height'],
            'alt' => $data['description'] ? $data['description'] : ($data['slug'] ? $data['slug'] : $data['name'])
        );
        if ($thumb_size == FALSE) {
            return $image;
        }
        $imagick = new Imagick(__TYPECHO_ROOT_DIR__ . $data['path']);
        if ($crop || $thumb_size['crop']) {
            $imagick->cropThumbnailImage($thumb_size['width'], $thumb_size['height']);
        } else {
            $imagick->resizeImage($thumb_size['width'], $thumb_size['height'], Imagick::FILTER_CATROM, 1, true);
        }
        //$imagick->setImageFormat($data['type']);
        //$imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
        $quality = $imagick->getImageCompressionQuality() * 0.75;
        $quality == 0 && $quality = 75;
        $imagick->setImageCompressionQuality($quality);
        $imagick->stripImage();
        if ($imagick->writeimage(__TYPECHO_ROOT_DIR__ . $data['save_path'])) {
            $imagick->clear();
            $imagick->destroy();
            $image['url'] = substr($data['save_path'], 1);
            $image['width'] = $thumb_size['width'];
            $image['height'] = $thumb_size['height'];
        }
        return $image;
    }

}
