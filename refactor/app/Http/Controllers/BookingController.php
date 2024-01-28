<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;
    private $auth_user;
    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
        $this->auth_user = auth()->user();
    }

    /**
     * @param Request $request
     * @return mixed
     */
  

    public function index(Request $request)
    {
        if ( $request->get('user_id')) {
            $response = $this->repository->getUsersJobs($request->user_id);
        } elseif ($request->user()->user_type == config('app.admin_role_id') || $request->user()->user_type == config('app.superadmin_role_id')) {
            $response = $this->repository->getAll($request);
        }

        return  response()->json(['result'=>$response]);
    }

    /**
     * @param $id
     * @return mixed
     */
  
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response()->json(['job'=>$job]);
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        
        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $this->auth_user);
        if($response){
            return response()->json(['message'=>'data has been updated successfully','result'=>$response]);
        }

        return response()->json(['message'=>'something went wrong']);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();
    
        $response = $this->repository->storeJobEmail($data);
    
        return response->json(['data'=>$response]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if( $request->user_id) {

            $response = $this->repository->getUsersJobsHistory($request->user_id, $request);
            return response->json(['data'=>$response]);
        }

        return response()->json(['data'=>null]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->acceptJob($data, $this->auth_user);

        return response->json(['data'=>$response]);
    }

    public function acceptJobWithId(Request $request)
    {

        $request->validate(['job_id' =>'required']);
        $data = $request->job_id;

        $response = $this->repository->acceptJobWithId($data, $this->auth_user);

        return response->json(['data'=>$response]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->cancelJobAjax($data, $this->auth_user);

        return response->json(['data'=>$response]);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response->json(['data'=>$response]);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response->json(['data'=>$response]);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
 
    {

        $response = $this->repository->getPotentialJobs($this->auth_user);

        return response->json(['data'=>$response]);
    }

    public function distanceFeed(Request $request)
{
    $validator = Validator::make($request->all(), [
        'distance' => 'nullable|string',
        'time' => 'nullable|string',
        'jobid' => 'nullable|string',
        'session_time' => 'nullable|string',
        'flagged' => 'nullable|boolean',
        'manually_handled' => 'nullable|boolean',
        'by_admin' => 'nullable|boolean',
        'admincomment' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 400);
    }

    $distance = $request->input('distance', '');
    $time = $request->input('time', '');
    $jobid = $request->input('jobid', '');
    $session = $request->input('session_time', '');

    $flagged = $request->input('flagged', false) ? 'yes' : 'no';
    $manuallyHandled = $request->input('manually_handled', false) ? 'yes' : 'no';
    $byAdmin = $request->input('by_admin', false) ? 'yes' : 'no';

    $admincomment = $request->input('admincomment', '');

    if ($time || $distance) {
        Distance::where('job_id', $jobid)->update(['distance' => $distance, 'time' => $time]);
    }

    if ($admincomment || $session || $flagged || $manuallyHandled || $byAdmin) {
        Job::where('id', $jobid)->update([
            'admin_comments' => $admincomment,
            'flagged' => $flagged,
            'session_time' => $session,
            'manually_handled' => $manuallyHandled,
            'by_admin' => $byAdmin
        ]);
    }

    return response->json(['message'=>'Record updated!']);
}
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response->json(['data'=>$response]);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response->json(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->validate([
            'jobid' => 'required|string',
        ]);
    
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
    
        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['error' => $e->getMessage()], 500);
        }
    }

}
