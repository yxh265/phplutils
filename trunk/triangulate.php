<?php
// http://wiki.tcl.tk/14581

# triangulate.tcl --
#     Construct a triangle network for a given set of points
#     The algorithm is somewhat naive as that it does not
#     guarantee any particular constraints on the triangles'
#     form or other properties other than that they cover
#     the convex hull of the point set, that each vertex
#     is a point from the set and that they do not overlap.
#     In particular nearly collinear points may cause very
#     narrow triangles.
#

class Triangulate {
	// triangles --
	//     Construct the network of triangles for a set of points
	// Arguments:
	//     points    List of coordinates (each point is a list of
	//               the x and y coordinates, e.g.
	//               {{1 1} {2 3} {1 4} {5 1}}
	// Result:
	//     A list of triangles (a triangle is represented as
	//     a list of three points)
	//
	static public function triangles($points) {
		if (count($points) <= 2) throw(new Exception("The set of points must have at least three points"));

		//
		// The trivial case
		// 
		if (count($points) <= 3) return array($points);

		$triangles = array();

		//
		// First step:
		//     Find the topmost point
		//
		$idx    = -1;
		$idxmax = 0;
		$ymax   = $points[0][1];
		foreach ($points as $idx => $p) {
			$y = $p[1];
			if ($y > $ymax) {
				$idxmax = $idx;
				$ymax   = $y;
			}
		}

		//
		// Second step:
		//     Find the two adjacent points on the convex hull
		//
		$idxleft  = static::FindHullPoint($points, $idxmax, 'left');
		$idxright = static::FindHullPoint($points, $idxmax, 'right');

		//
		// Third step:
		//     Determine which points are inside the triangle
		//     formed by (idxmax, idxleft, idxright) and form
		//     a convex arc
		//
		$innerpoints = static::FindArcPoints($points, $idxmax, $idxleft, $idxright);
		echo "$idxmax $idxleft $idxright: $innerpoints\n";

		// Fourth step:
		//     Construct the triangles
		//
		// @TODO
		/*
		foreach ($innerpoints as $idx) {
			lappend triangles \
			[list [lindex $points $idxmax]   \
			[lindex $points $idxleft]  \
			[lindex $points $idx]      ]
			set idxleft $idx
		}
		lappend triangles \
		[list [lindex $points $idxmax]   \
		[lindex $points $idxleft]  \
		[lindex $points $idxright] ]
		*/

		//
		// Now, the last step, remove the point "idxmax" and
		// repeat the procedure. Construct the list of all
		// triangles found this way.
		//
		/*
		set triangles [concat $triangles \
		[triangles [lreplace $points $idxmax $idxmax]]]
		*/

		return $triangles
	}
}

 namespace eval ::math::triangulate {
     variable torad [expr {180.0/(4.0*atan(1.0))}]
 }

 # FindHullPoint --
 #     Find the point on the convex hull adjacent to the given one
 # Arguments:
 #     points    List of coordinates
 #     idxc      Index of the given point
 #     dir       What direction (left or right)
 # Result:
 #     Index of the point that was sought
 #
 proc ::math::triangulate::FindHullPoint {points idxc dir} {
     foreach {xc yc} [lindex $points $idxc] {break}

     set idx        -1
     set anglehull 360.0
     if { $dir == "right" } {
         set anglehull 0.0
     }
     set idxhull   -1
     foreach p $points {
         incr idx
         if { $idx == $idxc } {
             continue
         }
         foreach {xp yp} [lindex $points $idx] {break}
         set angle [Angle $xc $yc $xp $yp]
         if { $dir == "left" } {
             if { $angle < $anglehull } {
                 set idxhull   $idx
                 set anglehull $angle
             }
         } else {
             if { $angle > $anglehull } {
                 set idxhull   $idx
                 set anglehull $angle
             }
         }
     }

     return $idxhull
 }

 # Angle --
 #     Determine the angle of a vector (in degrees)
 # Arguments:
 #     x1        X coordinate of start
 #     y1        Y coordinate of start
 #     x2        X coordinate of end
 #     y2        Y coordinate of end
 # Result:
 #     Angle to the x-axis
 #
 proc ::math::triangulate::Angle {x1 y1 x2 y2} {
     variable torad

     if { $x2 != $x1 || $y2 != $y1 } {
         set angle [expr {$torad*atan2($y2-$y1,$x2-$x1)}]

         # Corner case - important mainly for tests
         if { $y1 == $y2 && $x2 > $x1 } {
             set angle 360.0
         }
     } else {
         set angle 0.0 ;# Hm, this is a problem! Better raise an error
         error "Points are equal!"
     }
     if { $angle < 0.0 } {
         set angle [expr {360.0+$angle}]
     }
     return $angle
 }

 # FindArcPoints --
 #     Find the points inside the given triangle that form a convex arc
 #     from the left to the right
 # Arguments:
 #     points    List of coordinates
 #     idxmax    Index of the topmost point of the triangle
 #     idxleft   Index of the leftmost point of the triangle
 #     idxright  Index of the rightmost point of the triangle
 # Result:
 #     List of indices
 #
 proc ::math::triangulate::FindArcPoints {points idxmax idxleft idxright} {

     #
     # The procedure is very simple:
     # - Remove the topmost point from the set
     # - Look for points on the convex hull of this reduced set,
     #   starting at the left point of the triangle
     # - Continue until we reach the right point
     #
     # Note: here is an opportunity for optimisation - we
     #       need the reduced set later on.
     #
     set points [lreplace $points $idxmax $idxmax]

     set end         0
     set next        $idxleft
     set innerpoints {}
     while { ! $end } {
         set p [FindHullPoint $points $next right]
         if { $p != $idxright && $p != $idxleft } {
             lappend innerpoints [expr {$p>=$idxmax? $p+1 : $p}]
             set next $p
         } else {
             set end 1
         }
     }

     return $innerpoints
 }

 # test --
 #     Some points
 #
 set points {{0 1} {-1 0} {1 0} {0 -1} {-2 -1} {2 -1}}
 puts [::math::triangulate::triangles $points]