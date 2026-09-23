// Unit-test the real submit handler without a browser automation framework.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('teacher/upload_check.php', 'utf8');
const start = source.indexOf("document.getElementById('ocrUploadForm').addEventListener('submit'");
const script = source.slice(start, source.indexOf('</script>', start));
async function run(mode) {
  let handler, calls = 0, sent, rendered = 0;
  const button = {disabled:false, innerHTML:''};
  const elements = Object.fromEntries(['examSelect','studentSelect','ocrFeedback','ocrResult','examFileInput'].map(id=>[id,{value:'1',innerHTML:'',textContent:'',scrollIntoView(){}}]));
  const form = {addEventListener(_,fn){handler=fn;},querySelector(){return button;}};
  elements.ocrUploadForm = form;
  class FormDataMock {constructor(){this.values=new Map([['csrf_token','test'],['exam_id','1'],['student_id','1'],['process_ocr_grading','1']]);}delete(k){this.values.delete(k);}set(k,v){this.values.set(k,v);}append(k,v){this.values.set(k,(this.values.get(k)||[]).concat(v));}}
  const ctx = vm.createContext({document:{getElementById:id=>elements[id]},capturedPages:[{blob:'page1',filename:'1.png'},{blob:'page2',filename:'2.png'},{blob:'page3',filename:'3.png'}],selectedFileObjects:[],FormData:FormDataMock,console:{error(){}},alert(){throw Error('Unexpected validation alert');},renderPagesTray(){rendered++;},
    fetch:async (_,options)=>{calls++;sent=options.body;if(mode==='network')throw Error('Network failed');return {redirected:mode==='expired',status:mode==='limit'?413:200,text:async()=>''};},
    DOMParser:class {parseFromString(){return {getElementById:id=>id==='ocrFeedback'?{innerHTML:mode==='limit'?'Upload exceeds limit':''}:{innerHTML:'result',textContent:mode==='limit'?'':'Submission ID: #123'}};}}
  });
  vm.runInContext(script,ctx);
  const event={preventDefault(){}};
  handler.call(form,event);
  handler.call(form,event); // A second event during the same pending request must not resubmit.
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(calls,1);
  assert.equal(sent.values.get('process_ocr_grading'),'1');
  assert.equal(sent.values.get('exam_files[]').length,3);
  assert.equal(button.disabled,false);
  if(mode==='success'){assert.equal(ctx.capturedPages.length,0);assert.equal(rendered,1);assert.equal(elements.ocrResult.innerHTML,'result');}
  else {assert.equal(ctx.capturedPages.length,3);assert.equal(rendered,0);assert.ok(elements.ocrFeedback.innerHTML || elements.ocrFeedback.textContent);}
  console.log('PASS: '+mode+' preserves action, sends all pages, handles result, prevents duplicate and restores button');
}
(async()=>{for(const mode of ['success','limit','network','expired'])await run(mode);})().catch(e=>{console.error(e);process.exitCode=1;});
