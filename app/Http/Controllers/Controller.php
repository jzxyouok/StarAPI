<?php

namespace App\Http\Controllers;

use App\Exceptions\DynamicValidationException;
use Illuminate\Http\Request;
use App\Profile;
use App\Validation;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\AclHelper;

/**
 * Class Controller
 * @package App\Http\Controllers
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $request;

    /**
     * Controller constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonSuccess($data, array $headers = [])
    {
        $headers = $this->appendAuthHeaders($headers);

        return response()->json($data, 200, $headers);
    }

    /**
     * @param $errors
     * @param int $statusCode
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonError($errors, $statusCode = 400, array $headers = [])
    {
        $response = [];
        if (!is_array($errors)) {
            $errors = [$errors];
        }

        $response['error'] = true;
        $response['errors'] = $errors;

        $headers = $this->appendAuthHeaders($headers);

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * @param array $headers
     * @return array
     */
    private function appendAuthHeaders(array $headers)
    {
        //Authenticate user
        $token = JWTAuth::getToken();
        if ($token) {
            $headers = array_merge(
                $headers,
                [
                    'Authorization' => 'bearer ' . $token
                ]
            );
        }

        return $headers;
    }

    /**
     * @param $fields
     * @param $resourceName
     * @param array $inputOverrides
     * @return bool
     * @throws DynamicValidationException
     */
    protected function validateInputsForResource(&$fields, $resourceName, array $inputOverrides = [])
    {
        $user = \Auth::user();

        //Validations per user role
        if ($user && $user->admin === true) {
            return true;
        }

        $validationModel = Validation::where('resource', $resourceName)
            ->first();

        if (!$validationModel instanceof Validation) {
            throw new MethodNotAllowedHttpException([], 'Validation definition is missing for this resource.');
        }

        $validations = $validationModel->getFields();

        $acl = AclHelper::getAcl($user);
        $userRole = $acl->name;

        // Check if validation rules exists for use role
        if (!key_exists($userRole, $validationModel->acl)) {
            return false;
        }

        switch ($this->request->method()) {
            case 'POST':
                if ($validationModel->acl[$userRole]['canCreate'] !== true) {
                    return false;
                }
                return true;

            case 'PUT':
                $allowedFields = [];
                $editableFields = $validationModel->acl[$userRole]['editable'];
                $editableFields = array_flip($editableFields);
                foreach ($fields as $field => $value) {
                    if (isset($editableFields[$field])) {
                        $allowedFields[$field] = $value;
                    }
                }
                $fields = $allowedFields;

                if (count(array_intersect_key(array_flip($editableFields), $fields)) !== count($fields)) {
                    return false;
                }
                return true;

            case 'GET':
                if ($validationModel->acl[$userRole]['canRead'] !== true) {
                    return false;
                }
                return true;

            case 'DELETE':
                if ($validationModel->acl[$userRole]['canDelete'] !== true) {
                    return false;
                }
                return true;
        }

        foreach ($inputOverrides as $field => $value) {
            $validations[$field] = $value;
        }

        $checkValidations = [];

        foreach ($validations as $field => $value) {
            if (!isset($fields[$field])) {
                continue;
            }

            $checkValidations[$field] = $value;
        }

        $validator = Validator::make(
            $fields,
            $checkValidations,
            $validationModel->getMessages()
        );

        if ($validator->fails()) {
            throw new DynamicValidationException($validator->errors()->all(), 400);
        }

        return true;
    }

    /**
     * @return Profile|null
     */
    protected function getCurrentProfile()
    {
        try {
            $profile = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            $profile = null;
        }

        return $profile;
    }
}
