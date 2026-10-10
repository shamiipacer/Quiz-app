"""Exercise the local API without printing passwords, tokens or verification signatures."""
import json,secrets,subprocess,urllib.request,urllib.error,time
base='http://127.0.0.1:8000'
token=None

def request(path,method='GET',data=None,expected=200):
    headers={'Accept':'application/json','Content-Type':'application/json'}
    if token: headers['Authorization']='Bearer '+token
    req=urllib.request.Request(base+path,data=None if data is None else json.dumps(data).encode(),headers=headers,method=method)
    try:
        response=urllib.request.urlopen(req,timeout=20)
    except urllib.error.HTTPError as e:
        response=e
    assert response.status==expected, f'{method} {path}: expected {expected}, got {response.status}'
    body=response.read()
    return json.loads(body) if body and response.headers.get('Content-Type','').startswith('application/json') else None

for attempt in range(20):
    try:
        request('/up'); break
    except (OSError,AssertionError):
        if attempt==19: raise
        time.sleep(0.5)
email='smoke-'+secrets.token_hex(6)+'@example.com';password=secrets.token_hex(12)+'Aa1'
r=request('/api/v1/register','POST',{'name':'Smoke Test','email':email,'password':password,'password_confirmation':password,'terms':True,'privacy':True,'timezone':'UTC'},201)
token=r['token']; user_id=r['user']['id']
request('/api/v1/questions/today',expected=403)
result=subprocess.run(['docker','compose','run','--rm','-T','api','php','/app/scripts/verification-url.php',str(user_id)],capture_output=True,text=True,check=True)
url=result.stdout.strip(); assert url.startswith(base)
with urllib.request.urlopen(url,timeout=20) as response: assert response.status==200
questions=request('/api/v1/questions/today')['data']; assert len(questions)==20
assert all('correct_answer' not in q['question'] and 'explanation' not in q['question'] for q in questions)
a=questions[0];answer=str(a['question']['options'][0]['id'])
request(f"/api/v1/questions/{a['id']}/answer",'POST',{'answer':'999999999'},422)
r=request(f"/api/v1/questions/{a['id']}/answer",'POST',{'answer':answer});assert r['correct'] and r['coins_awarded']==10
request(f"/api/v1/questions/{a['id']}/answer",'POST',{'answer':answer},409)
assert request('/api/v1/coins/balance')['balance']==10
request('/api/v1/logout','POST',{},204);request('/api/v1/profile',expected=401)
print('PASS: live MySQL API health, registration, signed verification, 20 private questions, option validation, grading, duplicate protection, balance, logout.')
