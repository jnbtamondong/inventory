(function(){
    'use strict';
    var canvas=document.getElementById('hospitalMotionCanvas');
    if(!canvas||!canvas.getContext)return;
    var context=canvas.getContext('2d');
    var reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var width=0,height=0,ratio=1,nodes=[],ripples=[],frame=0,timer=0;
    var pointer={x:-1000,y:-1000};

    function resize(){
        width=window.innerWidth;height=window.innerHeight;ratio=Math.min(window.devicePixelRatio||1,2);
        canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
        context.setTransform(ratio,0,0,ratio,0,0);
        var count=Math.max(24,Math.min(width<700?38:76,Math.round(width*height/17000)));
        nodes=Array.from({length:count},function(_,index){return{
            x:Math.random()*width,y:Math.random()*height,
            vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,
            radius:.9+Math.random()*1.7,cross:index%11===0
        }});
    }

    function medicalCross(node){
        var size=5.5;
        context.fillStyle='rgba(50,140,193,.38)';
        context.fillRect(node.x-size,node.y-1.2,size*2,2.4);
        context.fillRect(node.x-1.2,node.y-size,2.4,size*2);
    }

    function pulseLine(time){
        var base=height*.76,progress=(time*.035)%(width+240)-120;
        context.beginPath();context.moveTo(0,base);context.lineTo(Math.max(0,progress-58),base);
        context.lineTo(progress-42,base);context.lineTo(progress-30,base-16);
        context.lineTo(progress-15,base+22);context.lineTo(progress,base-40);
        context.lineTo(progress+17,base+10);context.lineTo(progress+32,base);
        context.lineTo(width,base);
        context.strokeStyle='rgba(50,140,193,.11)';context.lineWidth=1.1;context.stroke();
    }

    function draw(time){
        context.clearRect(0,0,width,height);pulseLine(time||0);
        for(var rippleIndex=ripples.length-1;rippleIndex>=0;rippleIndex--){
            var ripple=ripples[rippleIndex];ripple.radius+=3.2;ripple.opacity=(1-ripple.radius/ripple.max)*.62;
            context.beginPath();context.arc(ripple.x,ripple.y,ripple.radius,0,Math.PI*2);
            context.strokeStyle='rgba(50,140,193,'+Math.max(0,ripple.opacity*.7)+')';context.lineWidth=1.3;context.stroke();
            if(ripple.radius>=ripple.max)ripples.splice(rippleIndex,1);
        }
        nodes.forEach(function(node,index){
            if(!reduceMotion){
                node.x+=node.vx;node.y+=node.vy;if(node.x<0||node.x>width)node.vx*=-1;if(node.y<0||node.y>height)node.vy*=-1;
                var pointerX=node.x-pointer.x,pointerY=node.y-pointer.y,pointerDistance=Math.sqrt(pointerX*pointerX+pointerY*pointerY);
                if(pointerDistance>0&&pointerDistance<125){var force=(125-pointerDistance)/125;node.x+=(pointerX/pointerDistance)*force*1.15;node.y+=(pointerY/pointerDistance)*force*1.15}
            }
            if(node.cross)medicalCross(node);else{
                context.beginPath();context.arc(node.x,node.y,node.radius,0,Math.PI*2);
                context.fillStyle='rgba(50,140,193,.48)';context.fill();
            }
            for(var next=index+1;next<nodes.length;next++){
                var other=nodes[next],dx=node.x-other.x,dy=node.y-other.y,distance=Math.sqrt(dx*dx+dy*dy);
                if(distance<125){context.beginPath();context.moveTo(node.x,node.y);context.lineTo(other.x,other.y);context.strokeStyle='rgba(11,60,93,'+((1-distance/125)*.13)+')';context.lineWidth=.7;context.stroke()}
            }
        });
        if(!reduceMotion)frame=requestAnimationFrame(draw);
    }

    resize();draw(0);
    window.addEventListener('pointermove',function(event){pointer.x=event.clientX;pointer.y=event.clientY},{passive:true});
    window.addEventListener('pointerleave',function(){pointer.x=-1000;pointer.y=-1000},{passive:true});
    window.addEventListener('pointerdown',function(event){if(!reduceMotion)ripples.push({x:event.clientX,y:event.clientY,radius:5,max:105,opacity:.62})},{passive:true});
    window.addEventListener('resize',function(){clearTimeout(timer);timer=setTimeout(function(){cancelAnimationFrame(frame);resize();draw(0)},140)},{passive:true});
})();
