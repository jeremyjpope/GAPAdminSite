<?php

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Usage in routes: ['filter' => 'area:students'] */
class AreaFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! Auth::check()) {
            session()->set('intended', current_url());

            return redirect()->to('/login');
        }
        $area = $arguments[0] ?? '';
        if (! Auth::can($area)) {
            return redirect()->to('/')->with('error', 'You do not have access to that area.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
