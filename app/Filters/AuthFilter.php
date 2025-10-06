<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (!$session->get('isLoggedIn')) {
            $session->set('intended', current_url());
            return redirect()->to(site_url('login'));
        }
        if ($arguments && in_array('admin-only', $arguments, true)) {
            if (($session->get('role') ?? '') !== 'admin') {
                return service('response')->setStatusCode(403)->setBody('Forbidden');
            }
        }
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
