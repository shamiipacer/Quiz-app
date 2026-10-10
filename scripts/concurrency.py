import concurrent.futures,json,subprocess,time
base=['docker','compose','run','--rm','-T','-e','DB_DATABASE=quiz_test','migrate','php','/app/scripts/reward-worker.php']
def run(args):
 r=subprocess.run(base+args,capture_output=True,text=True,check=True)
 return r.stdout.strip()
data=json.loads(run(['prepare']));start=str(time.time()+5)
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
 results=list(pool.map(run,[['answer',str(data['assignment']),start],['answer',str(data['assignment']),start]]))
assert sorted(results)==['awarded','duplicate'],results
assert run(['verify',str(data['assignment'])])=='verified'
print('PASS: simultaneous MySQL reward submissions produced one answer, one 10-coin award and one duplicate rejection.')
