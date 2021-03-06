<?php
import("@.Action.CommonUtil");
class QuestionAction extends Action {
	public function _initialize(){
		$util = new CommonUtil();
		$util->autologin();
		$this->assign('current', 'question');
	}

	public function _empty(){
		$this->display("Public:404");
	}
					
	//获得最新解决的问题
	public function getLatestSolvedQuestion(){
		$Question = M('Question');
		$lsQuestions = $Question->order('create_time desc')->where('answer_count > 0')->limit(5)->select();
		return $lsQuestions;
	}

	public function adjustAnswerCount(){
		$Question = M('Question');
		$Answer = M('Answer');
		$questions = $Question->select();
		for($i=0; $i<count($questions); $i++){
			$answers = $Answer->where("q_id=".$questions[$i]['id'])->select();
			$answercount = count($answers);
			$adjustData['id'] = $questions[$i]['id'];
			$adjustData['answer_count'] = $answercount;
			$Question->save($adjustData);
		}
		echo 'done';
	}

    public function index(){
    	$type = I("param.type");
    	$domin = I("param.domin");

    	if(!$type){
    		$type = "全部";
    	}

    	if($type != "全部"){
    		$sql = "question.type = '".$type."'";
    	}else{
    		$sql = "";
    	}
    		
    	if($domin == "myself"){
    		$userId = session('userId');
    		$Model = M();
    		$map['u_id'] = $userId;
			$count = $Model->table('seu_question as question')->where($map)->count();
			if($count){
				$eachPageShowCount = 25;
				$pageCount = ceil($count/$eachPageShowCount);
				if(I('param.id')){
					$page = I('param.id');
					if($page > $pageCount) $page = $pageCount;
				}
				else{
					$page = 1;
				}
				$start = ($page-1)*$eachPageShowCount;
				
				$questions = $Model->table('seu_question as question')->order('create_time desc')->limit($start.','.$eachPageShowCount)->where($map)->select();

				for($i=0; $i<count($questions); $i++){
					$User = M('User');
					$result = $User->find($questions[$i]['u_id']);
					$questions[$i]['u_name'] = $result['name'];
				}			
			}
			$this->assign('domin', 'myself');
    	}else{
    		$Model = M();
			$count = $Model->table('seu_question as question')->where($sql)->count();
			if($count){
				$eachPageShowCount = 25;
				$pageCount = ceil($count/$eachPageShowCount);
				if(I('param.id')){
					$page = I('param.id');
					if($page > $pageCount) $page = $pageCount;
				}
				else{
					$page = 1;
				}
				$start = ($page-1)*$eachPageShowCount;
				
				$questions = $Model->table('seu_question as question')->order('create_time desc')->limit($start.','.$eachPageShowCount)->where($sql)->select();

				for($i=0; $i<count($questions); $i++){
					$User = M('User');
					$result = $User->find($questions[$i]['u_id']);
					$questions[$i]['u_name'] = $result['name'];
				}			
			}
			$this->assign('domin', 'all');
    	}
    	
		$Question = M('Question');
		$hotQuestions = $Question->order('click_count desc')->limit(10)->select();
		$this->assign('hots',$hotQuestions);
		$this->assign('questions',$questions);
		$this->assign('questionscount', count($questions));
		$this->assign('count',$count);
		$this->assign('type', $type);
		$this->assign('curr_page',$page);
		$this->assign('page_count',$pageCount);
		$this->assign('lsquestions', $this->getLatestSolvedQuestion());

		$this->display('index');
		
    }

    public function detail(){
		$id = I('param.id');
		$userId = session('userId');
		$aid = I('param.aid');
		if($aid){
			$this->assign('aid', $aid);
		} 

		//about notify message
		$deleteModel = new Model();
		$deleteResult = $deleteModel->execute('delete from seu_question_message where q_id='.$id.' and u_id='.$userId);
		$AnswerMessage = M('AnswerMessage');
		$AnswerMessage->where("q_id=".$id.' and u_id='.session('userId'))->delete();
		$AnswerAt = M('AnswerAt');
		$AnswerAt->where('q_id='.$id.' and u_id='.session('userId'))->delete();
		$AnswerAt->where('q_id='.$id.' and u_id=0')->delete();
		$AgreeMessage = M('AgreeMessage');
		$AgreeMessage->where("q_id=".$id.' and u_id='.session('userId'))->delete();
	
		/*$model = new Model();
		$questionMessageResult = $model->query('select * from seu_question_message where u_id='.session('userId'));
		$questionMessageCount = count($questionMessageResult);

		$eventMessageResult = $model->query('select * from seu_event_message where u_id='.session('userId'));
		$eventMessageCount = count($eventMessageResult);

		$commodityMessageResult = $model->query('select * from seu_commodity_message where u_id='.session('userId'));
		$commodityMessageCount = count($commodityMessageResult);

		$messageCount = $questionMessageCount + $eventMessageCount + $commodityMessageCount;

		session('messageCount', $messageCount);

		session('questionMessageResult', $questionMessageResult);
		session('eventMessageResult', $eventMessageResult);
		session('commodityMessageResult', $commodityMessageResult);*/

		//获取问题编号，然后更新问题的浏览数，浏览数+1
		$Question = M('Question');
		$add['id'] = $id;
		$add['click_count'] = array('exp','click_count+1');
		$Question->save($add);
		
		//获取问题编号之后获取问题信息，再获取问题的提问者编号
		$questionInfo = $Question->find($id);
		$questionInfo['intro'] = htmlspecialchars_decode($questionInfo['intro']);
 
		$User = M('User');
		$result = $User->find($questionInfo['u_id']);
		$questionInfo['u_name'] = $result['name'];
		$questionInfo['icon'] = $result['icon'];
		$questionInfo['u_intro'] = $result['intro'];

		$currentUser = $User->where('id='.$userId)->find();
		$this->assign('invited', $currentUser['invited']);

		$util = new CommonUtil();
		$questionInfo["u_sex"] = $util->filter_sex($result["sex"]);

		$this->assign('question',$questionInfo);

		$Model = M();
		//多表查询
		$AnswerInfo = $Model->table('seu_answer answer, seu_user user')->field('answer.*,user.name as u_name, user.icon as icon, user.sex as u_sex')->where("answer.q_id = $id AND answer.u_id = user.id AND answer.anonymous = 0")->order('answer.support_count desc')->select();
		$AnonymousInfo = $Model->query('select * from seu_answer where q_id='.$id.' and anonymous=1;');

		for($i=0; $i<count($AnonymousInfo); $i++){
			$AnonymousInfo[$i]['u_name'] = "雷锋";
			//判断当前用户是不是已经点过赞了
			$map['a_id'] = $AnonymousInfo[$i]['id'];
			$map['u_id'] = $userId;
			$EventAgree = M('SupportAnswer');
			if($EventAgree->where($map)->find()){
				$AnonymousInfo[$i]['currentUserAgree'] = 1;
			}
			else{
				$AnonymousInfo[$i]['currentUserAgree'] = 0;
			}
			//判断当前用户是不是已经点过差评了
			$EventObject = M('NonsupportAnswer');
			if($EventObject->where($map)->find()){
				$AnonymousInfo[$i]['currentUserObject'] = 1;
			}else{
				$AnonymousInfo[$i]['currentUserObject'] = 0;
			}
		}

		for($i=0; $i<count($AnswerInfo); $i++){
			//判断当前用户是不是已经点过赞了
			$map['a_id'] = $AnswerInfo[$i]['id'];
			$map['u_id'] = $userId;
			$EventAgree = M('SupportAnswer');
			if($EventAgree->where($map)->find()){
				$AnswerInfo[$i]['currentUserAgree'] = 1;
			}
			else{
				$AnswerInfo[$i]['currentUserAgree'] = 0;
			}

			//判断当前用户是不是已经点过差评了
			$EventObject = M('NonsupportAnswer');
			if($EventObject->where($map)->find()){
				$AnswerInfo[$i]['currentUserObject'] = 1;
			}else{
				$AnswerInfo[$i]['currentUserObject'] = 0;
			}

			$AnswerInfo[$i]['content'] = htmlspecialchars_decode($AnswerInfo[$i]['content']);

			//添加实名回答的所有评论和回复
			$AnswerReply = M('AnswerReply');
			$User = M('User');
			$replyMap['a_id'] = $AnswerInfo[$i]['id'];
			$replys = $AnswerReply->where($replyMap)->select();
			for($j=0; $j<count($replys); $j++){
				$replyUser = $User->where('id='.$replys[$j]['u_id'])->find();
				$replys[$j]['u_name'] = $replyUser['name'];
				$replys[$j]['u_id'] = $replyUser['id'];
				$replys[$j]['avatar'] = $replyUser['icon'];
				$replys[$j]['content'] = htmlspecialchars_decode($replys[$j]['content']);
			}
			$AnswerInfo[$i]['replys'] = $replys;
			$AnswerInfo[$i]['replycount'] = count($replys);
		}

		for($i=0; $i<count($AnonymousInfo); $i++){
			//添加匿名回答的所有评论和回复
			$AnswerReply = M('AnswerReply');
			$User = M('User');
			$replyMap['a_id'] = $AnonymousInfo[$i]['id'];
			$replys = $AnswerReply->where($replyMap)->select();
			for($j=0; $j<count($replys); $j++){
				$replyUser = $User->where('id='.$replys[$j]['u_id'])->find();
				$replys[$j]['u_name'] = $replyUser['name'];
				$replys[$j]['u_id'] = $replyUser['id'];
				$replys[$j]['avatar'] = $replyUser['icon'];
				$replys[$j]['content'] = htmlspecialchars_decode($replys[$j]['content']);
			}
			$AnonymousInfo[$i]['replys'] = $replys;
			$AnonymousInfo[$i]['replycount'] = count($replys);
			$AnonymousInfo[$i]['content'] = htmlspecialchars_decode($AnonymousInfo[$i]['content']);
		}

		if($AnswerInfo == null && $AnonymousInfo != null){
			$UltimateInfo = $AnonymousInfo;
		}
		if($AnswerInfo != null && $AnonymousInfo == null){
			$UltimateInfo = $AnswerInfo;
		}
		if($AnswerInfo != null && $AnonymousInfo != null){
			$UltimateInfo = array_merge($AnswerInfo, $AnonymousInfo);
		}

		foreach ($UltimateInfo as $key => $value) {
			$support_count[$key] = $value['support_count'];
			$create_time[$key] = $value['create_time'];
		}

		array_multisort($support_count, $create_time, $UltimateInfo);
		$UltimateInfo = array_reverse($UltimateInfo);

		$this->assign('answers', $UltimateInfo);

		//判断是否是当前用户关注的问题
		$map['u_id'] = $userId;
		$map['q_id'] = $id;
		$Focus = M('FocusQuestion');
		if($Focus->where($map)->find()){
			$this->assign('focus',1);
		}
		else{
			$this->assign('focus',0);
		}

		$hotQuestions = $Question->order('click_count desc')->limit(10)->select();
		$this->assign('hots',$hotQuestions);
		$this->assign('lsquestions', $this->getLatestSolvedQuestion());

		$this->display('detail_new');
	}

	public function addQuestion(){
		$userId=session('userId');
		if(isset($userId)){
			$data['title'] = I('param.title');
			$data['intro'] = I('param.intro');
			$type_id = I('param.typeid');
			$data['type_id'] = $type_id;
			$anonymous = I('param.anonymous');
			if($anonymous){
				$data['anonymous'] = 1;
			}else{
				$data['anonymous'] = 0;
			}

			switch($type_id){
				case 0:
					$data['type'] = '生活娱乐';
					break;
				case 1:
					$data['type'] = '学习考试';
					break;
				case 2:
					$data['type'] = '规章制度';
					break;
				case 3:
					$data['type'] = '技术专业';
					break;
				case 4:
					$data['type'] = '其他';
					break;
			}
			$data['u_id'] = session('userId');
			$data['create_time'] = time();

			$Question = M('Question');
			$id = $Question->add($data);
			if($id < 1){
				$this->ajaxReturn('', '', 0);
			}else{
				$result['id'] = $id;
				$this->ajaxReturn($result, '', 1);
			}
			//$this->redirect('detail', array('id'=>$id));
		}else{
			$this->ajaxReturn('', '', -1);
		}

	}

	public function moreSearch(){
		$content = I('param.content');
		$count = I('param.count');

		/*$model = new Model();
		$sql = "select title,id,create_time,answer_count from seu_question where title like '%".$content."%' order by create_time desc limit ".$count;

		$questionMessageResult = $model->query($sql);

		for($i=0; $i<count($questionMessageResult); $i++){
			$questionMessageResult[$i]['title'] = str_replace($content, "<span style=\"color:red\">".$content."</span>", $questionMessageResult[$i]['title']);
		}*/
		$questionMessageResult = search('seu_question',$content,$count,1);
		
		if(count($questionMessageResult) == $count){
			$this->ajaxReturn($questionMessageResult, 'more', 1);
		}
		else{
			$this->ajaxReturn($questionMessageResult, 'nomore', 1);
		}
		
	}

	public function changeContent(){
		$qid = I('param.q_id');
		$content = I('param.content');
		$title = I('param.title');
		$type = I('param.type');
		$Question = M('Question');
			
		$question = $Question->where("id=".$qid)->find();

		$questionSaveData['intro'] = $content;
		$questionSaveData['title'] = $title;
		$questionSaveData['type'] = $type;
		$result = $Question->where('id='.$qid)->save($questionSaveData);
		if($result){
			$this->ajaxReturn('', '', 1);
		}else{
			if($title == $question['title'] || $content == $question['intro'] || $type == $question['type']){
				$this->ajaxReturn('', '', 1);
			}else{
				$this->ajaxReturn('', '', 0);
			}
		}
	}

	public function search(){
		$content = session('search_question_content');
		if(isset($content) && ($content == I('content'))){
			$count = session('search_question_count');
		}
		else{
			$content = I('content');
			session('search_question_content',$content);
			$count = count(search('seu_question',$content,20,0));
			session('search_question_count',$count);
		}
		
		$page = I('page');
		$eachPageShowCount = 20;
		$pageCount = ceil($count/$eachPageShowCount);
		$questions = search('seu_question',$content,$eachPageShowCount,$page);
		
		//$questions = search('seu_question',"大一",$count,1);
		$Question = M('Question');
		$hotQuestions = $Question->order('click_count desc')->limit(10)->select();
		$this->assign('hots',$hotQuestions);
		$this->assign('lsquestions', $this->getLatestSolvedQuestion());
		$this->assign('questions',$questions);
		$this->assign('questionscount', count($questions));
		$this->assign('count',$count);
		//$this->assign('type', $type);
		$this->assign('curr_page',$page);
		$this->assign('page_count',$pageCount);
		$this->assign('content',$content);
		$this -> display();
	}
}
?>