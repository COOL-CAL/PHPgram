<?php
namespace application\controllers;

use application\libs\Application;

class UserController extends Controller {
    //로그인
    public function signin() {
        switch(getMethod()) {
            case _GET:
                return "user/signin.php";
            case _POST:
                $email = $_POST["email"];
                
                $param = [
                    "email" => $_POST["email"],
                    "pw" => $_POST["pw"]
                ];

                //GET(삭제)
                //값이 쿼리스트링으로 전달
                //= 기준 왼쪽이 key값, 오른쪽이 value값

                //POST(등록, 수정)
                //값이 body에 담겨져서 전달

                //값 저장하는 방식: 쿼리스트링, 배열
                //배열 -> sequence 유무
                //ex) $arr=[10, 20, 30]; <-있을 유.
                //    쿼리스트링 <-없을 무.

                //ArrayList -> 배열 스타일
                //1. 만들기 쉽고,
                //2. 용량이 적고,
                //3. 셀렉트 속도가 빠르다.

                //LinkedList -> node 스타일
                //1. 수정이 쉽다.


                $dbUser = $this->model->selUser($param);

                // if(!$dbUser) { //아이디 없음
                //     return "redirect:/user/signin";
                // } else if(!password_verify($param["pw"], $dbUser->pw)) {
                //     return "redirect:/user/signin";
                // }

                if(!$dbUser || !password_verify($param["pw"], $dbUser->pw)) {
                    return "redirect:signin?email={$email}&err";
                }

                $dbUser->pw = null;
                $dbUser->regdt = null;
                $this->flash(_LOGINUSER, $dbUser);

                return "redirect:/feed/index";
                }

        }
    
    //회원가입
    public function signup() {
        // if(getMethod() === _GET) {
        //     return "user/signup.php";
        // } else if(getMethod() === _POST) {
        //     return "redirect:signin";
        // }
        // or
        switch(getMethod()) {
            case _GET:
                return "user/signup.php";
            case _POST:
                $param = [
                    "email" => $_POST["email"],
                    "pw" => $_POST["pw"],
                    "nm" => $_POST["nm"]
                ];
                $param["pw"] = password_hash($param["pw"], PASSWORD_BCRYPT);
                $this->model->insUser($param);
                return "redirect:signin";
        }
    }

    public function logout() {
        $this->flash(_LOGINUSER);
        return "redirect:/user/signin";
    }

    // my feed
    public function feedwin() {
        $iuser = isset($_GET["iuser"]) ? intval($_GET["iuser"]) : 0;
        $param = [
            "feediuser" => $iuser,
            "loginiuser" => getIuser()
        ];
        $this->addAttribute(_DATA, $this->model->selUserProfile($param));
        $this->addAttribute(_JS, ["user/feedwin", "https://unpkg.com/swiper@8/swiper-bundle.min.js"]);
        $this->addAttribute(_CSS, ["user/feedwin" , "https://unpkg.com/swiper@8/swiper-bundle.min.css", "feed/index"]);
        $this->addAttribute(_MAIN, $this->getView("user/feedwin.php"));
        return "template/t1.php";
    }

    public function feed() {
        $iuser = isset($_GET["iuser"]) ? intval($_GET["iuser"]) : 0;
        if(getMethod() === _GET) {
            $page = 1;
            if(isset($_GET["page"])) {
                $page = intval($_GET["page"]);
            }
            $startIdx = ($page - 1) * _FEED_ITEM_CNT;
            $param = [
                "startIdx" => $startIdx,
                "toiuser" => $_GET["iuser"],
                "loginiuser" => getIuser()
            ];
            $list = $this->model->selFeedList($param);
            foreach($list as $item) {
                $param2 = ["ifeed" => $item->ifeed];
                $item->imgList = Application::getModel("feed")->selFeedImgList($param2);
                $item->cmt = Application::getModel("feedcmt")->selFeedCmt($param2);
            }
            return $list;
        } //static함수 -> static인 멤버필드에서만 사용.
    }

    public function follow() {
        $param = [
            "fromiuser" => getIuser()
        ];
        
        switch(getMethod()) {
            case _POST:
                $json = getJson();
                $param["toiuser"] = $json["toiuser"];
                return [_RESULT =>  $this->model->insUserFollow($param)];
                case _DELETE:
                $param["toiuser"] = $_GET["toiuser"];
                return [_RESULT => $this->model->delUserFollow($param)];
        }
    }

    public function profile() {
        switch(getMethod()) {
            case _POST:
                if(!isset($_FILES["profileImg"])) {
                    return [_RESULT => 0];
                }
                $loginUser = getLoginUser();
                
                if($loginUser) {
                    $path = _IMG_PATH . "/profile/" . $loginUser->iuser;
                    if(!is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    if($loginUser->mainimg) {
                        $savedImg = $path . "/" . $loginUser->mainimg;
                        if(file_exists($savedImg)) {
                            unlink($savedImg);
                        }
                    }
                    $tempName = $_FILES['profileImg']['tmp_name'];
                    $randomFileNm = getRandomFileNm($_FILES["profileImg"]["name"]);
                    $param = [
                        "iuser" => $loginUser->iuser,
                        "mainimg" => $randomFileNm
                    ];
                    if(move_uploaded_file($tempName, $path . "/" . $randomFileNm)) {
                        $this->model->updUser($param);
                        $loginUser->mainimg = $randomFileNm;
                        return [_RESULT => 1, "fileNm" => $randomFileNm];
                    }
                }

            case _DELETE:
                $loginUser = getLoginUser();
                if($loginUser) {
                    $path = "static/img/profile/{$loginUser->iuser}/{$loginUser->mainimg}";
                    if(file_exists($path) && unlink($path)) {
                        $param = ["iuser" => $loginUser->iuser, "delMainImg" => 1];
                        if($this->model->updUser($param)) {
                            return [_RESULT => 1];
                        }
                    }
                }
                return [_RESULT => 0];
        }
    }
}